<?php
// FILE: /tools/check-routes.php — verifies every route resolves using the
// Router's OWN resolveControllerClass(). Run: php tools/check-routes.php
// Route checker that uses the ROUTER'S OWN resolution logic.
$base = dirname(__DIR__);
require "$base/app/Core/Autoloader.php"; App\Core\Autoloader::register($base);
require "$base/app/Helpers/functions.php";
App\Core\App::boot($base); restore_error_handler(); restore_exception_handler();

$router=new App\Core\Router();
foreach(['auth'=>App\Middleware\AuthMiddleware::class,'guest'=>App\Middleware\GuestMiddleware::class,
 'csrf'=>App\Middleware\CsrfMiddleware::class,'admin'=>App\Middleware\AdminMiddleware::class,
 'reseller'=>App\Middleware\ResellerMiddleware::class,'client'=>App\Middleware\ClientMiddleware::class,
 'maintenance'=>App\Middleware\MaintenanceMiddleware::class,'api'=>App\Middleware\ApiAuthMiddleware::class] as $a=>$c)
 $router->aliasMiddleware($a,$c);
(require "$base/routes/web.php")($router);
(require "$base/routes/api.php")($router);

$missing=[];$ok=0;
foreach($router->getRoutes() as $route){
  $action=$route->getAction();
  if($action instanceof Closure){$ok++;continue;}
  if(is_string($action)&&str_contains($action,'@')) [$c,$m]=explode('@',$action,2);
  elseif(is_array($action)) [$c,$m]=$action;
  else {$missing[]="bad action";continue;}
  $fq=$router->resolveControllerClass($c);          // <-- router's real logic
  if(!class_exists($fq)){$missing[]=implode(' ',$route->getMethods())." ".$route->getUri()." -> CLASS MISSING: $fq";continue;}
  if(!method_exists($fq,$m)){$missing[]=$route->getUri()." -> METHOD MISSING: {$fq}::{$m}";continue;}
  $ok++;
}
echo "Routes checked: ".count($router->getRoutes())." | OK: $ok\n";
if($missing){echo "----- BROKEN (".count($missing).") -----\n".implode("\n",$missing)."\n";exit(1);}
echo "✅ EVERY ROUTE RESOLVES VIA ROUTER'S OWN LOGIC\n";
