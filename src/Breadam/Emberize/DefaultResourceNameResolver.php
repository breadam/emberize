<?php namespace Breadam\Emberize;

class DefaultResourceNameResolver implements ResourceNameResolverInterface {
	
	public function resolve($model){
		return strtolower(class_basename($model));
	}
	
}