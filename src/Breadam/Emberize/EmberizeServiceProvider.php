<?php namespace Breadam\Emberize;

use Illuminate\Support\ServiceProvider;

class EmberizeServiceProvider extends ServiceProvider {

	protected $defer = false;
	
	public function boot(){
		$this->package("breadam/emberize");
	}
	
	public function register(){
		
    $this->app->bind("emberize", function($app){
			
			$app->bind("\Breadam\Emberize\ResourceNameResolverInterface",$app["config"]->get("emberize::resolver"));
			
			return new Emberize(
				$app["config"]->get("emberize::identifier"),
				$app["config"]->get("emberize::resources"),
				$app["config"]->get("emberize::mode"),
				$app->make("\Breadam\Emberize\ResourceNameResolverInterface")
			);
		});
	}
}
