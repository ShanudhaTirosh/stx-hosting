<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsResponse;
use App\Mcp\Concerns\ResolvesTeam;
use App\Mcp\Servers\StxServer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class StxHelp extends Tool
{
    protected string $name = 'stx_help';

    protected string $description = 'Start here. Lists the STX Hosting MCP tools this token can use and step-by-step recipes for multi-step tasks. Optional: topic (recipe name) or search (filter tools).';

    use BuildsResponse;
    use ResolvesTeam;

    /**
     * Multi-step workflows. Each step names a registered tool (and action for grouped tools).
     *
     * @var array<string, array{description: string, steps: array<int, array{tool: string, action?: string, note: string}>}>
     */
    public const RECIPES = [
        'deploy_app_from_git' => [
            'description' => 'Create and deploy an application from a Git repository',
            'steps' => [
                ['tool' => 'list_projects', 'note' => 'Find the project, or create one with manage_project action=create.'],
                ['tool' => 'get_project', 'note' => 'Pick the environment (name or UUID).'],
                ['tool' => 'list_servers', 'note' => 'Pick a reachable server_uuid.'],
                ['tool' => 'list_destinations', 'note' => 'Needed only when the server has more than one destination.'],
                ['tool' => 'list_github_apps', 'note' => 'Private repo: use a GitHub app, or a deploy key (manage_private_key).'],
                ['tool' => 'create_application', 'action' => 'public', 'note' => 'Or action=github_app / deploy_key. Set git_repository, git_branch, build_pack, ports_exposes.'],
                ['tool' => 'manage_env', 'action' => 'bulk_update', 'note' => 'Set environment variables (resource=application).'],
                ['tool' => 'deploy', 'note' => 'Queue the first deployment.'],
                ['tool' => 'get_deployment', 'note' => 'Follow the status; include_log_summary=true on failure.'],
            ],
        ],
        'add_database_to_app' => [
            'description' => 'Create a database and connect an application to it',
            'steps' => [
                ['tool' => 'get_application', 'note' => 'Note the project, environment, and server of the app.'],
                ['tool' => 'create_database', 'action' => 'postgresql', 'note' => 'Use the same environment and server_uuid as the app.'],
                ['tool' => 'control', 'note' => 'Start the database (resource=database, action=start).'],
                ['tool' => 'get_database', 'note' => 'Read internal connection string (DB_URL, postgresql://...).'],
                ['tool' => 'manage_env', 'action' => 'set', 'note' => 'Set DATABASE_URL on the application.'],
                ['tool' => 'deploy', 'note' => 'Redeploy the application so the new variable takes effect.'],
            ],
        ],
        'inspect_failing_deployment' => [
            'description' => 'Diagnose a deployment failure',
            'steps' => [
                ['tool' => 'get_deployment', 'note' => 'Call with include_log_summary=true to read the extracted error excerpt.'],
                ['tool' => 'get_logs', 'note' => 'If the summary is not enough, fetch deployment logs (lines=200).'],
                ['tool' => 'get_application', 'note' => 'Check build pack, ports_exposes, base_directory, dockerfile.'],
                ['tool' => 'manage_env', 'action' => 'list', 'note' => 'Verify required build and runtime variables are set.'],
            ],
        ],
        'restore_database_backup' => [
            'description' => 'Safely restore a standalone database from an S3 or local backup',
            'steps' => [
                ['tool' => 'list_database_backups', 'note' => 'Locate the backup file by name, database_uuid, and created_at.'],
                ['tool' => 'control', 'note' => 'Action=stop to pause traffic before restore, or verify maintenance window.'],
                ['tool' => 'control', 'note' => 'Action=restore, database_uuid=..., filename=...'],
                ['tool' => 'get_database', 'note' => 'Confirm status returns to running.'],
            ],
        ],
    ];

    public function handle(Request $request): Response
    {
        $topic = $request->string('topic', '');
        $search = $request->string('search', '');

        if ($topic !== '') {
            if (isset(self::RECIPES[$topic])) {
                return $this->textResponse(json_encode([
                    'topic' => $topic,
                    'recipe' => self::RECIPES[$topic],
                ], JSON_PRETTY_PRINT));
            }

            return $this->errorResponse("Unknown topic '$topic'. Available topics: ".implode(', ', array_keys(self::RECIPES)));
        }

        $tools = collect(StxServer::tools())
            ->map(function ($toolClass) {
                $instance = new $toolClass;

                return [
                    'name' => $instance->name(),
                    'description' => $instance->description(),
                ];
            });

        if ($search !== '') {
            $tools = $tools->filter(function ($t) use ($search) {
                return Str::contains($t['name'], $search, true)
                    || Str::contains($t['description'], $search, true);
            });
        }

        return $this->textResponse(json_encode([
            'tools' => $tools->values(),
            'recipes' => array_keys(self::RECIPES),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('Optional recipe name (e.g. deploy_app_from_git, add_database_to_app).'),
            'search' => $schema->string()->description('Optional search term to filter tools by name or description.'),
        ];
    }
}
