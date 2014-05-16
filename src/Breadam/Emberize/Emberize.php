<?php namespace Breadam\Emberize;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emberize{
	
	private $configSideload;
	private $configIdentifier;
	
	private $configIdentifiers = array();
	
	private $configFields = array();
	private $globalFields;
	private $makeFields;

	private $sideload;
	
	private $root;
	private $parents;
	private $keys;
	private $store;
	
	
	public function __construct($sideload,$identifier,$models){
		
		$this->configSideload = isset($sideload)?$sideload:true;
		$this->configIdentifier = isset($identifier)?array():$identifier;
		$this->configModels = isset($models)?$models:array();
		
		foreach($this->configModels as $modelName => $config){
			
			if(isset($config["fields"])){
				$this->configFields[$modelName] = $config["fields"];
			}
			
			if(isset($config["identifier"])){
				$this->configIdentifiers[$modelName] = $config["identifier"];
			}
		}
		
		$this->globalFields = array();
		self::mergeFields($this->globalFields,$this->configFields);
	}
	
	public function make($mixed,array $fields = array(),$sideload = null){
		
		$this->makeFields = $this->globalFields;
		
		if(count($fields) > 0){
			self::mergeFields($this->makeFields,$fields);
		}
		
		if(is_null($sideload)){
			$this->sideload = $this->configSideload;
		}else{
			$this->sideload = $sideload;
		}
		
		$this->parents = array();
	
		if($mixed instanceof Model){
			
			$this->root = $mixed;
			$this->prepareModel($mixed);
			
		}else if($mixed instanceof Collection){
			foreach($mixed as $model){
				$this->prepareModel($model);
			}
		}
		return $this->store;
	}
	
	public function fields(array $fields){
		$this->globalFields = array();
		self::mergeFields($this->globalFields,$this->configFields);
		self::mergeFields($this->globalFields,$fields);
	}
	
	private function prepareModel(Model $model){
		
		foreach($this->parents as $parent){
			if(self::isModelSame($parent,$model)){
				return;
			}
		}
	
		array_push($this->parents,$model);
		
		$fields = $this->getFields($model);
		$ret = array();
		$attributes = $model->attributesToArray();
		
		foreach($fields as $index => $fieldName){
			if(isset($attributes[$fieldName])){
				$ret[$fieldName] = $attributes[$fieldName];
				unset($fields[$index]);
			}
		}
		
		$identifierKey = $this->getModelIdentifierKey($model);
		$identifierValue = $this->getModelIdentifierValue($model);
		
		$ret[$identifierKey] = $identifierValue;
		
		$this->prepareRelationsFor($model,$fields,$ret);
		
		$this->storeModel($model,$ret);
		array_pop($this->parents);
	}
	
	private function prepareRelationsFor(Model $model,$relations,&$attributes){
		
		foreach($relations as $relationName){
			
			$relation = $model->$relationName();
			
			if($relation instanceof BelongsTo){
				unset($attributes[$relation->getForeignKey()]);
			}
			
			$result = $relation->getResults();
			
			if($result instanceof Model){
				
				$attributes[$relationName] = $this->getModelIdentifierValue($result);
				
				if($this->sideload){
					$this->prepareModel($result);
				}
				
			}else if($result instanceof Collection){
				
				$keys = $this->getCollectionIdentifierValues(str_singular($relationName),$result);
				
				if(count($keys) === 0){
					continue;
				}
				
				$attributes[$relationName] = $keys;
				
				if($this->sideload){				
					foreach($result as $item){
						$this->prepareModel($item);
					}
				}
			}
		}
		
	}
	
	private function storeModel(Model $model,$fields){
		
		$modelName = self::modelName($model);
		
		if($this->root == $model){
		
			$this->store[$modelName] = $fields;
			
		}else{
			
			$modelKey = $this->getModelIdentifierValue($model);
			
			if(isset($this->root)){
			
				$rootName = self::modelName($this->root);
				$rootKey = $this->getModelIdentifierValue($this->root);
				
				
				if($rootName == $modelName && $rootKey == $modelKey){
					return;
				}
			}
			
			$sideloadName = str_plural($modelName);
			
			if(isset($this->keys[$sideloadName]) && isset($this->keys[$sideloadName][$modelKey])){
				return;
			}
			
			$this->store[$sideloadName][] = $fields;
			$this->keys[$sideloadName][$modelKey] = true;
		}
	}
	
	private function getFields(Model $model){
		
		$modelName = self::modelName($model);
		
		if(isset($this->makeFields[$modelName])){
			return $this->makeFields[$modelName];
		}
		
		return array();		
	}
	
	private function getModelIdentifierKey(Model $model){
		
		$modelName = self::modelName($model);
		
		if(isset($this->configIdentifiers[$modelName])){
			
			$identifier = $this->configIdentifiers[$modelName];
			
			if(isset($identifier["key"])){
				return $identifier["key"];
			}
			
		}
		
		if(isset($this->configIdentifier["key"])){
			return $this->configIdentifier["key"];
		}
		
		return $model->getKeyName();
	}
	
	private function getModelIdentifierValue(Model $model){
		
		$modelName = self::modelName($model);
		
		if(isset($this->configIdentifiers[$modelName])){
			
			$identifier = $this->configIdentifiers[$modelName];
			
			if(isset($identifier["value"])){
				return $model->getAttribute($identifier["value"]);
			}
			
		}
		
		if(isset($this->configIdentifier["value"])){
			$key = $this->configIdentifier["value"];
			return $model->getAttribute($key);
		}
		
		return $model->getKey();
	}
	
	private function getCollectionIdentifierValues($modelName,Collection $collection){
		
		if(isset($this->configIdentifiers[$modelName])){
			
			$identifier = $this->configIdentifiers[$modelName];
			
			if(isset($identifier["value"])){
				return $collection->lists($identifier["value"]);
			}
		}
		
		if(isset($this->configIdentifier["value"])){
			$key = $this->configIdentifier["value"];
			return $collection->lists($key);
		}
		
		return $collection->modelKeys();
	}
	
	private static function mergeFields(array &$result,array $fields){
		
		if(count($fields) === 0){
			return;
		}
		
		foreach($fields as $model => $array){
			
			$arr = array();
			
			if(isset($result[$model])){
				$arr = $result[$model];
			}
			
			self::includeModelFields($arr,$array);
			
			$result[$model] = $arr;
		}
	}
	
	private static function includeModelFields(array &$result,array $array){
		
		$issetInc = isset($array["include"]);
		$issetExc = isset($array["exclude"]);
		
		if($issetInc || $issetExc){
			if($issetInc){
				$result = array_merge($result,$array["include"]);
			}
			if($issetExc){
				$result = array_diff($result,$array["exclude"]);
			}
		}else{
		
			$result = array_merge($result,$array);
			
		}
		
	}
	
	private static function modelName(Model $model,$plural = false){
		
		$name = strtolower(class_basename($model));
		
		if($plural){
			return str_plural($name);
		}
		
		return $name;
	}
	
	private static function isModelSame(Model $a,Model $b){
		
		if(self::modelName($a) != self::modelName($b)){
			return false;
		}
		
		return $a->id == $b->id;
	}
}