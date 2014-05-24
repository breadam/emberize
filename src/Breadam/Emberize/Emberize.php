<?php namespace Breadam\Emberize;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emberize{
	
	private $configIdentifier;
	private $configIdentifiers = array();
	private $configFields = array();
	private $configModes = array();
	
	private $globalModes;
	private $makeModes;
	
	private $globalFields;
	private $makeFields;
	
	private $root;
	private $parents;
	private $keys;
	private $store;
	
	public function __construct($identifier,$models){
		
		$this->configIdentifier = isset($identifier)?array():$identifier;
		
		foreach($models as $modelName => $config){
			
			if(isset($config["fields"])){
				$this->configFields[$modelName] = $config["fields"];
			}
			
			if(isset($config["identifier"])){
				$this->configIdentifiers[$modelName] = $config["identifier"];
			}
			
			if(isset($config["modes"])){
				$this->configModes[$modelName] = $config["modes"];
			}
		}
		
		$this->globalFields = array();
		self::mergeFields($this->globalFields,$this->configFields);
		
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
			$resource = $this->prepareModel($mixed);
			$this->storeRoot(self::modelName($mixed),$resource);
			
		}else if($mixed instanceof Collection){
		
			foreach($mixed as $model){
			
				$resource = $this->prepareModel($model);
				$this->storeSideload($mixed,$resource);
				
			}
		}
		return $this->store;
	}
	
	public function sideload($sideload = null){
		if(!is_null($sideload)){
			$this->sideload = $sideload;
		}
		return $this->sideload;
	}
	
	public function fields(array $fields,$merge = false){
		if($merge == false){
			$this->globalFields = array();
			self::mergeFields($this->globalFields,$this->configFields);
		}
			
		self::mergeFields($this->globalFields,$fields);		
	}
	
	public function modes(array $modes){
		self::mergeModes($this->globalModes,$modes);		
	}
	
	private function prepareModel(Model $model){
		
		if($this->isParent($model)){
			return;
		}
		
		$this->addParent($model);
		
		$modelName = self::modelName($model);
		$fields = $this->getFields($modelName);
		$attributes = $model->attributesToArray();
		$resource = array();
		
		foreach($fields as $index => $fieldName){
		
			if(isset($attributes[$fieldName])){
				$resource[$fieldName] = $attributes[$fieldName];
				unset($fields[$index]);
			}
		}
		
		$identifierKey = $this->getModelIdentifierKey($model);
		$identifierValue = $this->getModelIdentifierValue($model);
		
		$resource[$identifierKey] = $identifierValue;
		
		$this->prepareRelationsFor($model,$fields,$resource);
		
		$this->removeParent($model);
		
		return $resource;
	}
	
	private function prepareRelationsFor(Model $model,$relations,&$attributes){
		
		$modelName = self::modelName($model);
		
		foreach($relations as $relationName){
			
			$relation = $model->$relationName();
			
			if($relation instanceof BelongsTo){
				unset($attributes[$relation->getForeignKey()]);
			}
			
			$result = $relation->getResults();
			$mode = $this->getMode($modelName,$relationName);
			
			if($mode === "embed"){
				
				if($result instanceof Model){
					
					$resource = $this->prepareModel($result);
					
					if(is_null($resource)){
						return;
					}
					
					$attributes[$relationName] = $resource;
					
				}else if($result instanceof Collection){
					
					$attributes[$relationName] = array();
					
					foreach($result as $item){
						$resource = $this->prepareModel($item);
					
						if(is_null($resource)){
							continue;
						}
						
						$attributes[$relationName][] = $resource;
					}
					
				}
				
			}else {
				
				if($mode === "link"){
				
					if(!isset($attributes["links"])){
						$attributes["links"] = array();
					}
						
					$attributes["links"][$relationName] = str_plural($modelName)."/".$this->getModelIdentifierValue($model)."/".$relationName;	
				}
				
				if($result instanceof Model){
				
					$attributes[$relationName] = $this->getModelIdentifierValue($result);
					
					if($mode === "sideload"){
						
						$this->storeSideload($result,$this->prepareModel($result));
						
					}
				}else if($result instanceof Collection){
					
					$keys = $this->getCollectionIdentifierValues(str_singular($relationName),$result);
					
					if(count($keys) === 0){
						continue;
					}
					
					$attributes[$relationName] = $keys;
					
					if($mode === "sideload"){
						
						foreach($result as $item){
							$this->storeSideload($item,$this->prepareModel($item));
						}
						
					}
				}
			}
		}
	}
	
	private function storeRoot($modelName,$resource){
		$this->store[$modelName] = $resource;
	}
	
	private function storeSideload(Model $model,$resource){
		
		$modelName = self::modelName($model);
		
		if(isset($this->root) && self::isModelSame($model,$this->root)){
			return;
		}
			
		$sideloadName = str_plural($modelName);
		$modelKey = $this->getModelIdentifierValue($model);
		
		if(isset($this->keys[$sideloadName]) && isset($this->keys[$sideloadName][$modelKey])){
			return;
		}
		
		$this->store[$sideloadName][] = $resource;
		$this->keys[$sideloadName][$modelKey] = true;
	}
	
	private function getMode($modelName,$relationName){
		
		if(isset($this->globalModes[$modelName]) && isset($this->globalModes[$modelName][$relationName])){
			return $this->globalModes[$modelName][$relationName];
		}
	}
	
	private function getFields($modelName){
		
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
	
	private static function mergeModes(array &$result,array $modes){
		
		if(count($modes) === 0){
			return;
		}
		
		foreach($modes as $modelName => $array){
			
			$arr = array();
			
			if(isset($result[$modelName])){
				$arr = $result[$modelName];
			}
			
			self::mergeModelModes($arr,$array);
			
			$result[$modelName] = $arr;
		}
	}
	
	private static function mergeModelModes(array &$result,array $modes){
		$result = array_merge($result,$modes);
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
			
			self::mergeModelFields($arr,$array);
			
			$result[$model] = $arr;
		}
	}
	
	private static function mergeModelFields(array &$result,array $fields){
		
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
		
		return $a->getKey() == $b->getKey();
	}

}