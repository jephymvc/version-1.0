<?php
namespace App\Core;

use App\Core\GraphQL\GraphQL;
use App\GraphQL\Schema;
use App\Core\{ Controller, Config };
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

abstract class GraphQLBaseController extends Controller
{
    
	protected GraphQL $graphql;
    protected array $graphqlContext = [];
    protected bool $debug;
    protected string $endpoint;
    protected string $sandboxTitle;
    protected $schemaRegistered = false;
    
    public function __construct()
    {
        
		// Bypass CSRF protection for GraphQL endpoints
        $this->bypassCSRFProtection();
		
		// Call parent constructor to initialize Smarty, hooks, etc.
        parent::__construct();
        
        // Get project root (jephy-mvc folder)
        $projectRoot = $this->appPath;
        
        try {
            
			// Initialize GraphQL
            $this->graphql = GraphQL::getInstance( $projectRoot );            
            // Register schema if class exists
            $this->registerSchema();
            
        } catch (\Exception $e) {
            error_log("GraphQL initialization error: " . $e->getMessage());
            if ($this->debug) {
                throw $e;
            }
        }
        
        // Get debug setting from config
        $this->debug = $this->globalData ? 
            $this->globalData->get('graphql.debug', false) : 
            false;
        
        // Set endpoint URL
        $this->endpoint = $this->getEndpointUrl();        
        // Initialize authentication context
        $this->initializeGraphQLContext();
		
    }
	
	/**
     * Bypass CSRF protection for GraphQL requests
     */
    protected function bypassCSRFProtection(): void
    {
        // Remove CSRF token from POST data if present
        if (isset($_POST['csrf_token'])) {
            unset($_POST['csrf_token']);
        }
        
        // Clear CSRF related server variables
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        
        if (isset($_SERVER['HTTP_X_CSRF_PROTECTION'])) {
            unset($_SERVER['HTTP_X_CSRF_PROTECTION']);
        }
        
        // Set a flag to skip CSRF verification if your framework uses one
        define('SKIP_CSRF_VERIFICATION', true);
        
        // If your framework has a global CSRF protection function
        if (function_exists('disable_csrf_protection')) {
            disable_csrf_protection();
        }
		
    }
    
	
	/**
     * Set the sandbox page title
     */
    protected function setSandboxTitle(string $title = "" ): void
    {
        $this->sandboxTitle = $title == "" ? Config::getInstance()->get( "site.name", "JephyPHP MVC" ): $title;
    }
    
    /**
     * Register GraphQL schema
     */
    protected function registerSchema(): void
    {
        // Check if Schema class exists
        if ( class_exists( 'App\\GraphQL\\Schema' ) ) {
            Schema::register($this->graphql);
            $this->schemaRegistered = true;
        } else {
            // Schema class doesn't exist yet, log warning
            error_log('Warning: App\GraphQL\Schema class not found. Please create it or register queries/mutations manually.');
            
            // Register basic queries if needed (optional fallback)
            $this->registerFallbackSchema();
        }
		
    }
    
    /**
     * Register fallback schema for testing (optional)
     */
    protected function registerFallbackSchema(): void
    {
        // Only register if no custom schema and we're in debug mode
        if ( !$this->schemaRegistered && $this->debug ) {
            $this->registerBasicQueries();
        }
    }
    
    /**
     * Register basic queries for testing (optional)
     */
    protected function registerBasicQueries(): void
    {
        $testType = new ObjectType([
            'name' => 'Test',
            'fields' => [
                'message' => Type::string(),
                'status' => Type::string()
            ]
        ]);
        
        $queries = [
            'test' => [
                'type' => $testType,
                'resolve' => function() {
                    return [
                        'message' => 'GraphQL is working!',
                        'status' => 'OK'
                    ];
                }
            ],
            'health' => [
                'type' => Type::string(),
                'resolve' => function() {
                    return 'GraphQL endpoint is healthy';
                }
            ]
        ];
        
        $this->graphql->registerQueries($queries);
        $this->schemaRegistered = true;
    }
    
    /**
     * Get GraphQL endpoint URL
     */
    protected function getEndpointUrl(): string
    {
        // Detect if using HTTPS
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        // Get host
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get base path
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        
        return "{$protocol}://{$host}{$basePath}/graphql";
    }
    
    /**
     * Initialize GraphQL context with authentication
     */
    protected function initializeGraphQLContext(): void
    {
        // Try to get authenticated user from JWT or session
        $user = $this->getGraphQLAuthenticatedUser();
        
        $this->graphqlContext = [
            'user' => $user,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => session_id(),
            'request_time' => time(),
            'is_authenticated' => $user !== null
        ];
        
        if ($this->graphql) {
            $this->graphql->setContext($this->graphqlContext);
        }
    }
    
    /**
     * Get authenticated user for GraphQL
     */
    protected function getGraphQLAuthenticatedUser(): ?array
    {
        // First try JWT from Authorization header
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                if (method_exists($this->jwtService, 'validateToken')) {
                    $payload = $this->jwtService->validateToken($token);
                    if (isset($payload['user_id'])) {
                        return $this->getUserArray($payload['user_id']);
                    }
                }
            } catch (\Exception $e) {
                if ($this->debug) {
                    error_log("JWT validation failed: " . $e->getMessage());
                }
            }
        }
        
        // Then try session
        if ($this->isLoggedIn('user')) {
            $loginData = $this->getLoginData('user');
            if (isset($loginData['id'])) {
                return $this->getUserArray($loginData['id']);
            }
        }
        
        return null;
    }
    
    /**
     * Get user as array (for context)
     * Override this method to fetch from your database
     */
    protected function getUserArray($userId): ?array
    {
        // This is example data - replace with your actual User model
        // Example: return User::find($userId)->toArray();
        
        $users = [
            1 => ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'ADMIN', 'permissions' => ['post:create', 'post:update', 'post:delete']],
            2 => ['id' => 2, 'name' => 'Regular User', 'email' => 'user@example.com', 'role' => 'USER', 'permissions' => ['post:create']]
        ];
        
        return $users[$userId] ?? null;
    }
    
    /**
     * Handle GraphQL request (main entry point)
     */
    public function handle(): void
    {
        // Check if GraphQL is initialized
        if (!$this->graphql) {
            $this->json(['error' => 'GraphQL not initialized. Check your configuration.'], 500);
            return;
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Handle OPTIONS for CORS
        if ($method === 'OPTIONS') {
            $this->sendCorsHeaders();
            http_response_code(200);
            return;
        }
        
        $this->sendCorsHeaders();
        
        // Handle different request methods
        switch ($method) {
            case 'GET':
                $this->handleGetRequest();
                break;
            case 'POST':
                $this->handlePostRequest();
                break;
            default:
                http_response_code(405);
                $this->json(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Handle GET requests (sandbox and introspection)
     */
    protected function handleGetRequest(): void
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isHtmlRequest = strpos($acceptHeader, 'text/html') !== false;
        
        // Check if this is an introspection query via GET
        if (isset($_GET['query'])) {
            $this->executeGraphQL($_GET['query'], $_GET['variables'] ?? null);
            return;
        }
        
        // Show sandbox for browser requests
        if ($isHtmlRequest) {
            $this->showSandbox();
            return;
        }
        
        // Return API info for non-browser requests
        $this->json([
            'name' => 'Jephy MVC GraphQL API',
            'version' => '1.0.0',
            'endpoint' => $this->endpoint,
            'sandbox' => $this->endpoint . '/sandbox',
            'introspection' => true,
            'schema_registered' => $this->schemaRegistered
        ]);
    }
    
    /**
     * Handle POST requests (execute GraphQL)
     */
    protected function handlePostRequest(): void
    {
        $input = $this->request->input();
        
        if (!$input) {
            // Try to get from php://input
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
        }
        
        if (!$input || !isset($input['query'])) {
            $this->json(['error' => 'Invalid request. Query parameter required.'], 400);
            return;
        }
        
        $query = $input['query'];
        $variables = $input['variables'] ?? [];
        
        $this->executeGraphQL($query, $variables);
    }
    
    /**
     * Execute GraphQL query and output result
     */
    protected function executeGraphQL(string $query, $variables = []): void
    {
        if (empty($query)) {
            $this->json(['error' => 'No query provided'], 400);
            return;
        }
        
        if (!$this->graphql) {
            $this->json(['error' => 'GraphQL not initialized'], 500);
            return;
        }
        
        $result = $this->graphql->execute($query, $variables);
        
        // Add execution info in debug mode
        if ($this->debug && !isset($result['errors'])) {
            $result['extensions'] = [
                'debug' => [
                    'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
                    'memory_usage' => memory_get_usage(),
                    'schema_registered' => $this->schemaRegistered,
                    'context' => [
                        'user_authenticated' => $this->graphqlContext['is_authenticated'] ?? false,
                        'user_role' => $this->graphqlContext['user']['role'] ?? null
                    ]
                ]
            ];
        }
        
        $this->json($result);
    }
    
    /**
     * Show GraphQL sandbox using Smarty template
     */
    public function showSandbox(): void
    {
        // Check if Smarty template exists
        $templatePath = $this->appPath . '/app/Views/graphql/sandbox.tpl';
        
        if (!file_exists($templatePath)) {
            // Fallback to embedded sandbox
            $this->showEmbeddedSandbox();
            return;
        }
        
        // Prepare data for the template
        $data = [
            'title' => 'GraphQL Sandbox - Jephy MVC',
            'endpoint' => $this->endpoint,
            'debug' => $this->debug,
            'default_query' => $this->getDefaultQuery(),
            'default_variables' => $this->getDefaultVariables(),
            'examples' => $this->getQueryExamples(),
            'current_user' => $this->graphqlContext['user'] ?? null,
            'is_authenticated' => $this->graphqlContext['is_authenticated'] ?? false,
            'schema_registered' => $this->schemaRegistered,
            'available_tokens' => [
                ['name' => 'Admin Token', 'token' => 'valid-admin-token', 'role' => 'ADMIN'],
                ['name' => 'User Token', 'token' => 'valid-user-token', 'role' => 'USER'],
                ['name' => 'No Token (Guest)', 'token' => '', 'role' => 'GUEST']
            ]
        ];
        
        // Render the sandbox template
        $this->display('graphql/sandbox.tpl', $data);
    }
    
    /**
     * Show embedded sandbox (fallback when Smarty template not found)
     */
    protected function showEmbeddedSandbox(): void
    {
        $endpoint = $this->endpoint;
        $defaultQuery = $this->getDefaultQuery();
        $defaultVariables = $this->getDefaultVariables();
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>GraphQL Sandbox - Jephy MVC</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: monospace; background: #1a1a2e; color: #eee; height: 100vh; display: flex; flex-direction: column; }
                .header { background: #16213e; padding: 15px 20px; border-bottom: 1px solid #0f3460; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
                .header h1 { font-size: 18px; }
                .badge { background: #e94560; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
                .container { display: flex; flex: 1; padding: 15px; gap: 15px; overflow: hidden; }
                .panel { flex: 1; display: flex; flex-direction: column; background: #0f3460; border-radius: 8px; overflow: hidden; }
                .panel-header { background: #16213e; padding: 10px 15px; font-weight: bold; }
                textarea { flex: 1; background: #1a1a2e; border: none; color: #eee; font-family: monospace; padding: 15px; resize: none; outline: none; }
                .response-area { flex: 1; background: #1a1a2e; padding: 15px; overflow: auto; white-space: pre-wrap; }
                .button-bar { padding: 12px 20px; background: #16213e; display: flex; gap: 10px; border-top: 1px solid #0f3460; }
                button { background: #e94560; border: none; color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; }
                button:hover { background: #ff6b8b; }
                .status { background: #16213e; padding: 6px 20px; font-size: 12px; border-top: 1px solid #0f3460; }
                .success { color: #4ec9b0; }
                .error { color: #f48771; }
                .token-select { background: #0f3460; border: 1px solid #e94560; color: white; padding: 4px 8px; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🔍 GraphQL Sandbox <span class="badge">Jephy MVC</span></h1>
                <div>
                    <select id="tokenSelect" class="token-select" onchange="setToken(this.value)">
                        <option value="">Select Token</option>
                        <option value="valid-admin-token">Admin Token</option>
                        <option value="valid-user-token">User Token</option>
                    </select>
                </div>
            </div>
            <div class="container">
                <div class="panel">
                    <div class="panel-header">📝 Query</div>
                    <textarea id="queryEditor" placeholder="Enter GraphQL query...">' . htmlspecialchars($defaultQuery) . '</textarea>
                    <div class="panel-header">📦 Variables (JSON)</div>
                    <textarea id="variablesEditor" placeholder=\'{"id": 1}\'>' . htmlspecialchars($defaultVariables) . '</textarea>
                </div>
                <div class="panel">
                    <div class="panel-header">📊 Response</div>
                    <div id="responseArea" class="response-area">Click Execute to run query</div>
                </div>
            </div>
            <div class="button-bar">
                <button onclick="executeQuery()">▶ Execute (Ctrl+Enter)</button>
                <button onclick="clearResponse()">🗑 Clear</button>
            </div>
            <div class="status">
                <span id="statusMessage">Ready</span>
                <span id="timestamp"></span>
            </div>
            <script>
                const endpoint = \'' . $endpoint . '\';
                let currentToken = \'\';
                
                function setToken(token) { currentToken = token; updateStatus(\'Token \' + (token ? \'set\' : \'cleared\'), \'success\'); }
                async function executeQuery() {
                    const query = document.getElementById(\'queryEditor\').value;
                    const varsRaw = document.getElementById(\'variablesEditor\').value;
                    if (!query.trim()) { updateStatus(\'Please enter a query\', \'error\'); return; }
                    let variables = {};
                    if (varsRaw.trim()) { try { variables = JSON.parse(varsRaw); } catch(e) { updateStatus(\'Invalid JSON\', \'error\'); return; } }
                    updateStatus(\'Executing...\', \'\');
                    const start = performance.now();
                    try {
                        const headers = { \'Content-Type\': \'application/json\' };
                        if (currentToken) headers[\'Authorization\'] = \'Bearer \' + currentToken;
                        const resp = await fetch(endpoint, { method: \'POST\', headers, body: JSON.stringify({ query, variables }) });
                        const duration = (performance.now() - start).toFixed(2);
                        const result = await resp.json();
                        document.getElementById(\'responseArea\').textContent = JSON.stringify(result, null, 2);
                        updateStatus(result.errors ? `Errors in ${duration}ms` : `Success in ${duration}ms`, result.errors ? \'error\' : \'success\');
                        document.getElementById(\'timestamp\').textContent = new Date().toLocaleTimeString();
                    } catch(e) { updateStatus(\'Network error\', \'error\'); document.getElementById(\'responseArea\').textContent = \'Error: \' + e.message; }
                }
                function clearResponse() { document.getElementById(\'responseArea\').textContent = \'Response cleared...\'; updateStatus(\'Cleared\', \'\'); }
                function updateStatus(msg, type) { 
                    const el = document.getElementById(\'statusMessage\');
                    el.textContent = msg;
                    el.className = type === \'success\' ? \'success\' : (type === \'error\' ? \'error\' : \'\');
                }
                document.addEventListener(\'keydown\', (e) => { if ((e.ctrlKey || e.metaKey) && e.key === \'Enter\') executeQuery(); });
            </script>
        </body>
        </html>';
    }
    
    /**
     * Get default GraphQL query for sandbox
     */
    protected function getDefaultQuery(): string
    {
        if ($this->schemaRegistered) {
            return <<<'GRAPHQL'
				# Welcome to Jephy MVC GraphQL Sandbox
				#
				# Queries
				query GetMe {
				  me {
					id
					name
					email
					role
				  }
				}

				query GetPosts {
				  posts(limit: 10) {
					id
					title
					content
					likes
				  }
				}

				# Mutations
				mutation CreatePost($input: CreatePostInput!) {
				  createPost(input: $input) {
					id
					title
					content
				  }
				}
				GRAPHQL;
						} else {
							return <<<'GRAPHQL'
				# Welcome to GraphQL Sandbox
				#
				# Test query (schema not yet registered)
				query {
				  test {
					message
					status
				  }
				}

				# Health check
				query {
				  health
				}
				GRAPHQL;
        }
    }
    
    /**
     * Get default variables for sandbox
     */
    protected function getDefaultVariables(): string
    {
        return json_encode([
            'id' => 1,
            'input' => [
                'title' => 'My Post',
                'content' => 'Post content here'
            ]
        ], JSON_PRETTY_PRINT);
    }
    
    /**
     * Get query examples
     */
    protected function getQueryExamples(): array
    {
        if ($this->schemaRegistered) {
            return [
                'Get Current User' => 'query { me { id name email role } }',
                'Get All Posts' => 'query { posts { id title likes } }',
                'Create Post' => 'mutation CreatePost($input: CreatePostInput!) { createPost(input: $input) { id title } }'
            ];
        } else {
            return [
                'Test Query' => 'query { test { message status } }',
                'Health Check' => 'query { health }'
            ];
        }
    }
    
    /**
     * Send CORS headers
     */
    protected function sendCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * Override json method to handle GraphQL responses properly
     */
    protected function json($data, $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        
        if ($this->debug && defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $jsonFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }
        
        echo json_encode($data, $jsonFlags);
        exit;
    }
}
