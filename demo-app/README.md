# Demo host app

A minimal, real Laravel application used to exercise the BPM Engine
package end-to-end (not just through Orchestra Testbench), matching the
"Consuming Laravel Application" in Context & Scope (arc42 Section 3) and
the web/app-server + queue-worker roles in the Deployment View
(Section 7) — see the docs-toolkit's served site
(`http://localhost:8000`) for both.

This directory is intentionally empty except for this README, the
`Dockerfile`, and `AGENT_INSTRUCTIONS.md` — a full Laravel skeleton is
fetched via Composer, not hand-authored.

**Continuing the implementation after bootstrap?** See
[AGENT_INSTRUCTIONS.md](AGENT_INSTRUCTIONS.md) for the scenario to build
(Expense Reimbursement) and what to read first.

## One-time bootstrap

```
docker compose run --rm demo-app sh -c "composer create-project laravel/laravel /tmp/laravel --prefer-dist --no-interaction && cp -rn /tmp/laravel/. . && rm -rf /tmp/laravel"
```

(`composer create-project` refuses to install into a non-empty directory, and this
one already has `Dockerfile` and `README.md` in it — so this installs to a scratch
directory inside the container first, then merges the generated files back with
`cp -n`, which skips anything that would collide with the two files already here.)

Then add the package as a local path dependency. In the generated
`composer.json`, add:

```json
"repositories": [
    { "type": "path", "url": "../package" }
]
```

(Inside the container the package is bind-mounted at `/package`, a
sibling of `/app` — see `../docker-compose.yml` — so the relative path
`../package` resolves correctly.)

Then:

```
docker compose run --rm demo-app composer require lobstar/bpm-engine:@dev
docker compose run --rm demo-app php artisan migrate
docker compose up demo-app demo-app-worker postgres
```

`demo-app` serves the app (`php artisan serve`); `demo-app-worker` runs
`php artisan queue:work`, consuming batched transition jobs dispatched by
`QueueDispatcher` (see Runtime View, arc42 Section 6).

## Testing the MCP server interactively

The domain-verbed MCP tools (`SubmitExpense`, `ApproveExpense`,
`RejectExpense`, `EscalateToFinance`, `OpenDispute` — ADR-006) are served
at `POST /mcp/expenses` (`routes/ai.php`), behind `auth:sanctum`. Every
request needs a bearer token for a real `User`, since each tool
authorizes via the app's own Policies (ADR-005) before calling into the
package.

### 1. Generate a Sanctum token

```
docker compose run --rm demo-app php artisan tinker --execute='
use App\Models\User;
use App\Enums\UserRole;
$user = User::firstOrCreate(["email" => "employee@example.com"],
    ["name" => "Test Employee", "password" => bcrypt("password"), "role" => UserRole::Employee]);
echo $user->createToken("manual-test")->plainTextToken;
'
```
or
```
# ./app/scripts/generate-sanctum-token.sh
```

Swap `UserRole::Employee` for `Manager`/`Finance`/`Investigator`/
`FinanceDirector` to test a different role's authorization boundary —
each tool checks the acting user's role and, for most actions, that
they're the specific manager/submitter on the expense report.

### 2. Raw HTTP (no extra tooling)

With `demo-app` up (`docker compose up demo-app`, published on host port
8001):

```
# Initialize a session — capture the MCP-Session-Id response header
curl -i -X POST http://localhost:8001/mcp/expenses \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl-test","version":"0.0.1"}}}'

# List tools (reuse the MCP-Session-Id from the response above)
curl -X POST http://localhost:8001/mcp/expenses \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" -H "MCP-Session-Id: <session-id>" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'

# Call a tool
curl -X POST http://localhost:8001/mcp/expenses \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" -H "MCP-Session-Id: <session-id>" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"submit-expense","arguments":{"amount":42.50,"category":"software","manager_id":2}}}'
```

While the package is stubbed, a call that passes authorization currently
returns `{"isError":true,"text":"Not implemented yet."}` — that proves
the wiring, not the package's behavior (see
`docs/gap-analysis/revision-resolution.md`). A call that fails
authorization (wrong role, wrong owner, wrong state) returns
`{"isError":true,"text":"Permission denied."}` without ever reaching the
package.

### 3. MCP Inspector (visual, in its own container)

Add to `../docker-compose.yml`:

```yaml
  # Visual, interactive MCP client for exercising demo-app's tools by
  # hand. Runs on the same network as demo-app so it can reach it by
  # service name.
  mcp-inspector:
    image: ghcr.io/modelcontextprotocol/inspector:latest
    container_name: bpm-engine-mcp-inspector
    environment:
      HOST: "0.0.0.0"
      MCP_AUTO_OPEN_ENABLED: "false"
    ports:
      - "6274:6274"
      - "6277:6277"
    depends_on:
      - demo-app
```

Then `docker compose up mcp-inspector` and open `http://localhost:6274`.
To configure the connection: **Add Servers** → **Import from registry config**
Past the mcp-inspector.config.json file into **File Contents** → **Add Server**
Then in the new server panel goto **Settings** → **Custom Headers** → **+Add Header**

| Header | Value |
|---|---|
|`Authorization` | `Bearer <token>` |

### 4. Skip HTTP entirely (fastest for logic-only checks)

Call a Tool class directly, bypassing JSON-RPC and the session handshake:

```
docker compose run --rm demo-app php artisan tinker --execute='
Auth::loginUsingId(1);
$response = app(App\Mcp\Tools\SubmitExpense::class)->handle(new Laravel\Mcp\Request(["amount"=>42.5,"category"=>"software","manager_id"=>2]));
echo $response->isError() ? "ERROR: " : "OK: ";
echo (string) $response->content();
'
```
