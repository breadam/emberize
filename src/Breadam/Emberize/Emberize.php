<?php namespace Breadam\Emberize;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Emberize{
	
	private $configIdentifier;
	private $configIdentifiers = array();
	private $configFields = array();
	private $configModes = array();
	private $configPoly = array();
	private $configCase = "camel";
	
	private $globalModes;
	private $makeModes;
	
	private $globalFields;
	private $makeFields;
	
	private $root;
	private $parents;
	private $keys;
	private $store;
	
	private $resourceNameResolver;
	
	public function __construct($identifier,$resources,$mode,ResourceNameResolverInterface $resourceNameResolver,$case){
		
		$this->configIdentifier = isset($identifier)?array():$identifier;
		$this->configMode = $mode;
		$this->resourceNameResolver = $resourceNameResolver;
		$this->configCase = $case;
		
		// extract fields, identifier and modes from models config to separate arrays.. configFields, configIdentifiers, configModes
		
		foreach($resources as $resourceName => $config){
			
			if(isset($config["fields"])){
			
				list($names,$modes,$poly) = self::parseFields($config["fields"],$this->configMode);
				
				$this->configFields[$resourceName] = $names;
				$this->configModes[$resourceName] = $modes;
				$this->configPoly[$resourceName] = $poly;
			}
			
			if(isset($config["identifier"])){
				$this->configIdentifiers[$resourceName] = $config["identifier"];
			}
		}
		
		// prepare global fields
		$this->globalFields = array();
		self::mergeFields($this->globalFields,$this->configFields);
		
		
		// prepare global modes
		$this->globalModes = array();
		self::mergeModes($this->globalModes,$this->configModes);
	}
	
	public function make($mixed,array $fields = array()){
		
		$this->makeFields = $this->globalFields;
		
		if(count($fields) > 0){
			self::mergeFields($this->makeFields,$fields);
		}
		
		$this->resetParents();
	
		if($mixed instanceof Model){
			
			$this->root = $mixed;
			$resource = $this->prepareResource($mixed);
			$this->storeRoot($this->resourceName($mixed),$resource);
			
		}else if($mixed instanceof Collection){
		
			foreach($mixed as $model){
			
				$resource = $this->prepareResource($model);
				$this->storeSideload($model,$resource);
				
			}
		}
		return $this->store;
	}
		
	public function fields(array $fields,$merge = false){
		if($merge == false){ // reset globalFields
			
			$this->globalFields = array();
			self::mergeFields($this->globalFields,$this->configFields);
		}
			
		self::mergeFields($this->globalFields,$fields);	// merge fields with globalFields
	}
	
	public function modes(array $modes){
		self::mergeModes($this->globalModes,$modes);
	}
	
	private function prepareResource(Model $model){
	
		if($this->isParent($model)){ // if model is in parents array then it will get processed. just return. prevent inf loop.
			return;
		}
		
		$this->addParent($model); // add model to parents array to prevent infinite recursion
		
		$resourceName = $this->resourceName($model);
		
		$fields = $this->getFields($resourceName);
		
		$attributes = $model->attributesToArray();
		$resource = array();
		
		foreach($fields as $index => $fieldName){
			
			if(array_key_exists($fieldName,$attributes)){
				$resource[$this->convertCase($fieldName)] = $attributes[$fieldName];
				unset($fields[$index]);
			}
		}
		
		$identifierKey = $this->getModelIdentifierKey($model);
		$identifierValue = $this->getModelIdentifierValue($model);
		
		$resource[$identifierKey] = $identifierValue;
		
		foreach($fields as $fieldName){
			
			$field = $model->$fieldName();
			$convertedFieldName = $this->convertCase($fieldName);
			
			
			if(!($field instanceof Collection || $field instanceof Model) && $field instanceof Relation){
				
				$relation = $field;
				
				if($relation instanceof BelongsTo){
				
					unset($resource[$relation->getForeignKey()]);
					
				}else if($relation instanceof morphTo){
			
					unset($resource[$fieldName."_type"]);
					unset($resource[$fieldName."_id"]);
				
				}
				
				$field = $relation->getResults();
			}
			
			$mode = $this->getMode($resourceName,$fieldName);
			
			if($mode == "embed"){
				
				if($field instanceof Model){
					
					$fieldResource = $this->prepareModel($result);
					
					if(is_null($fieldResource)){
						return;
					}
					
					$resource[$convertedFieldName] = $fieldResource;
					
				}else if($field instanceof Collection){
					
					$resource[$convertedFieldName] = array();
					
					foreach($field as $item){
					
						$fieldResource = $this->prepareResource($item);
					
						if(is_null($fieldResource)){
							continue;
						}
						
						$resource[$convertedFieldName][] = $fieldResource;
					}
				}
				
			}else{
				
				if($mode === "link"){
				
					if(!isset($resource["links"])){
						$resource["links"] = array();
					}
						
					$resource["links"][$convertedFieldName] = /*str_plural($resourceName)."/".$this->getModelIdentifierValue($model)."/".*/$convertedFieldName;
					
				}
				
				if($field instanceof Model){
					
					if($this->isPolymorphic($resourceName,$fieldName)){
						
						$resource[$fieldName] = array(
							"type" => strtolower(class_basename($field)),
							"id" => $this->getModelIdentifierValue($field)
						);
						
					}else{
					
						$resource[$fieldName] = $this->getModelIdentifierValue($field);
					}
					
					if($mode === "sideload"){
						
						$this->storeSideload($field,$this->prepareModel($field));
					}
					
				}else if($field instanceof Collection){
					
					if($this->isPolymorphic($resourceName,$fieldName)){
					
						$resource[$convertedFieldName] = array();
						
						foreach($field as $model){
							$resource[$fieldName][] = array(
								"type" => $model->{str_singular($fieldName)."_type"},
								"id" => $model->{str_singular($fieldName)."_id"}
							);
						}
						
					}else{
					
						$keys = $this->getCollectionIdentifierValues(str_singular($fieldName),$field);
						
						if(count($keys) === 0){
							continue;
						}	
						
						$resource[$convertedFieldName] = $keys;
					}
					
					if($mode === "sideload"){
						
						foreach($field as $item){
							$this->storeSideload($item,$this->prepareResource($item));
						}
						
					}
				}
			}
		}
		
		$this->removeParent($model);
		
		return $resource;
	}
	
	private function isPolymorphic($resourceName,$fieldName){
		return isset($this->configPoly[$resourceName]) && in_array($fieldName,$this->configPoly[$resourceName],true);
	}
	
	private function storeRoot($resourceName,$resource){
		$this->store[$resourceName] = $resource;
	}
	
	private function storeSideload(Model $model,$resource){
		
		if(isset($this->root) && self::isModelSame($model,$this->root)){
			return;
		}
			
		$sideloadName = $this->resourceName($model,true);
		$modelKey = $this->getModelIdentifierValue($model);
		
		if(isset($this->keys[$sideloadName]) && isset($this->keys[$sideloadName][$modelKey])){
			return;
		}
		
		$this->store[$sideloadName][] = $resource;
		$this->keys[$sideloadName][$modelKey] = true;
	}
	
	private function getMode($resourceName,$relationName){
		
		if(isset($this->globalModes[$resourceName]) && isset($this->globalModes[$resourceName][$relationName])){
			return $this->globalModes[$resourceName][$relationName];
		}
	}
	
	private function getFields($resourceName){
		if(isset($this->makeFields[$resourceName])){
			return $this->makeFields[$resourceName];
		}
		return array();		
	}
	
	private function getModelIdentifierKey(Model $model){
		
		$resourceName = $this->resourceName($model);
		
		if(isset($this->configIdentifiers[$resourceName])){
			
			$identifier = $this->configIdentifiers[$resourceName];
			
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
		
		$resourceName = $this->resourceName($model);
		
		if(isset($this->configIdentifiers[$resourceName])){
			
			$identifier = $this->configIdentifiers[$resourceName];
			
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
	
	private function getCollectionIdentifierValues($resourceName,Collection $collection){
		
		if(isset($this->configIdentifiers[$resourceName])){
			
			$identifier = $this->configIdentifiers[$resourceName];
			
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
	
	private function resetParents(){
		$this->parents = array();
	}
	
	private function isParent(Model $model){
		foreach($this->parents as $parent){
			if(self::isModelSame($parent,$model)){
				return true;
			}
		}
		return false;
	}
	
	private function addParent(Model $model){
		array_push($this->parents,$model);
	}
	
	private function removeParent(){
		array_pop($this->parents);
	}
	
	private function resourceName($model,$plural = false){
	
		$name = $this->resourceNameResolver->resolve($model);
		
		if($plural){
			$name = str_plural($name);
		}
		return $name;
	}
	
	private static function parseFields(array $fields,$default = null){
		
		$names = array();
		$modes = array();
		$poly = array();
		
		foreach($fields as $fieldConfig){
			$arr = explode(":",$fieldConfig);
			
			$name = $arr[0];
			$names[] = $name;
			
			if(count($arr) > 1){
				$modes[$name] = $arr[1];
			}else if(!is_null($default)){
				$modes[$name] = $default;
			}
			
			if(count($arr) === 3){
				$poly[] = $name;
			}
		}
		
		return array($names,$modes,$poly);
	}
	
	private static function mergeModes(array &$result,array $modes){
		
		if(count($modes) === 0){
			return;
		}
		
		foreach($modes as $resourceName => $array){
			
			$arr = array();
			
			if(isset($result[$resourceName])){
				$arr = $result[$resourceName];
			}
			
			$arr = array_merge($arr,$array);
			$result[$resourceName] = $arr;
		}
	}
	
	private static function mergeFields(array &$result,array $fields){
		
		if(count($fields) === 0){
			return;
		}
		
		foreach($fields as $resourceName => $array){
			
			$arr = array();
			
			if(isset($result[$resourceName])){
				$arr = $result[$resourceName];
			}
			
			self::mergeResourceFields($arr,$array);
			
			$result[$resourceName] = $arr;
		}
	}
	
	private static function mergeResourceFields(array &$result,array $fields){
		
		$issetInc = isset($fields["include"]);
		$issetExc = isset($fields["exclude"]);
		
		if($issetInc || $issetExc){
			if($issetInc){
				$result = array_merge($result,$fields["include"]);
			}
			if($issetExc){
				$result = array_diff($result,$fields["exclude"]);
			}
		}else{
		
			$result = array_merge($result,$fields);
			
		}
		
	}
		
	private function convertCase($str){
		if($this->configCase == "snake"){
			return snake_case($str);
		}	else if($this->configCase == "camel"){
			return camel_case($str);
		}
		return $str;
	}
		
	private static function isModelSame(Model $a,Model $b){
		
		if(class_basename($a) != class_basename($b)){
			return false;
		}
		
		return $a->getKey() == $b->getKey();
	}
	
}