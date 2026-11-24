<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePermissionMiddleware extends Command
{
    protected $signature = 'permissions:generate-middleware {controller?} {--all}';
    protected $description = 'إنشاء middleware للصلاحيات في Controllers تلقائياً';

    // قائمة بالصلاحيات حسب الـ CRUD
    protected $permissionMap = [
        'index' => 'view',
        'show' => 'view',
        'create' => 'add',
        'store' => 'add',
        'edit' => 'edit',
        'update' => 'edit',
        'destroy' => 'delete',
        'delete' => 'delete',
    ];

    public function handle()
    {
        if ($this->option('all')) {
            $this->info('🔄 جاري معالجة جميع Controllers...');
            $this->processAllControllers();
        } else {
            $controller = $this->argument('controller');
            if (!$controller) {
                $controller = $this->ask('أدخل اسم Controller (مثل: ProductController)');
            }
            $this->processController($controller);
        }

        return Command::SUCCESS;
    }

    protected function processAllControllers()
    {
        $paths = [
            app_path('Http/Controllers'),
            base_path('Modules/*/Http/Controllers'),
        ];

        $controllers = [];
        foreach ($paths as $path) {
            $files = File::glob($path . '/*.php');
            $controllers = array_merge($controllers, $files);
        }

        $this->info("تم العثور على " . count($controllers) . " Controller");
        
        foreach ($controllers as $controllerPath) {
            $this->processControllerFile($controllerPath);
        }

        $this->info('✅ تم الانتهاء من معالجة جميع Controllers');
    }

    protected function processController($controllerName)
    {
        $this->info("معالجة: {$controllerName}");
        // Implementation here
    }

    protected function processControllerFile($filePath)
    {
        $content = File::get($filePath);
        
        // Check if already has __construct with middleware
        if (str_contains($content, 'public function __construct()') && 
            str_contains($content, 'middleware(\'permission:')) {
            $this->line("⏭️  تم تخطي: " . basename($filePath) . " (يحتوي على middleware بالفعل)");
            return;
        }

        $this->line("✏️  معالجة: " . basename($filePath));
        
        // Extract controller methods
        preg_match_all('/public function (\w+)\s*\(/', $content, $matches);
        $methods = $matches[1];

        if (empty($methods)) {
            return;
        }

        // Generate middleware code
        $middlewareCode = $this->generateMiddlewareCode($methods);

        // Insert or update __construct
        if (str_contains($content, 'public function __construct()')) {
            // Update existing constructor
            $pattern = '/(public function __construct\(\)[^{]*\{)/';
            $replacement = "$1\n{$middlewareCode}\n";
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            // Add new constructor after class declaration
            $pattern = '/(class\s+\w+\s+extends\s+\w+[^{]*\{)/';
            $replacement = "$1\n\n    public function __construct()\n    {\n{$middlewareCode}\n    }\n";
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Backup original file
        File::copy($filePath, $filePath . '.backup');
        
        // Write updated content
        File::put($filePath, $content);
        
        $this->info("✅ تم تحديث: " . basename($filePath));
    }

    protected function generateMiddlewareCode($methods)
    {
        $middlewareLines = [];
        $groupedMethods = [];

        foreach ($methods as $method) {
            if ($method === '__construct' || $method === '__invoke') {
                continue;
            }

            $permissionType = $this->permissionMap[$method] ?? null;
            if ($permissionType) {
                if (!isset($groupedMethods[$permissionType])) {
                    $groupedMethods[$permissionType] = [];
                }
                $groupedMethods[$permissionType][] = $method;
            }
        }

        foreach ($groupedMethods as $permType => $methodsList) {
            $methodsStr = "['" . implode("', '", $methodsList) . "']";
            $middlewareLines[] = "        \$this->middleware('permission:{$permType}_resource', ['only' => {$methodsStr}]);";
        }

        return implode("\n", $middlewareLines);
    }
}
