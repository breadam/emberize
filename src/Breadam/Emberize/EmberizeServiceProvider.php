<?php namespace Breadam\Emberize;

use Illuminate\Support\ServiceProvider;

class EmberizeServiceProvider extends ServiceProvider {

	protected $defer = false;
	
	public function boot(){
		$this->package("breadam/emberize");
	}
	
	public function register(){
	
    $this->app->bind("emberize", function($app){
			return new Emberize(
				$app["config"]->get("emberize::sideload"),
				$app["config"]->get("emberize::identifier"),
				$app["config"]->get("emberize::models")
			);
		});
	}
}
