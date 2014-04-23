<?php namespace Breadam\Emberize\Facades;

use Illuminate\Support\Facades\Facade;

class Emberize extends Facade{

	protected static function getFacadeAccessor(){ 
		return "emberize"; 
	}
	
}