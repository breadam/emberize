#  Laravel 4 response generator for Ember.

### Emberize in a nutshell	

	1. Usage: Emberize::make($model or $collection)
	2. Sideload,embed,links
 	2. (Not Yet)Polymorphic relationships. 
 	3. Public keys(GUID, email, username, etc as id)
 	4. Change attributes,relationships dynamically(If authorized then include user relationship in json)         

### Installation

	1. Add the following to your composer.json "require" array 
    	
        "breadam/emberize": "dev-master"

	2. Add the following to your app/config/app.php "providers" array

        "Breadam\Emberize\EmberizeServiceProvider"
	
	3. Update composer
        
        composer update
    
    4. Publish config file 
        
        php artisan config:publish breadam/emberize
    
    5. Edit app/config/packages/breadam/emberize/config.php to your needs

### Quick Example

    -- Database --
    
    table foos(id,public_key,name,bar_id)
    
    table bars(id,bar_specific_public_key,name)
        
    table buses(id,public_key,name,foo_id)
    
    -- Models --
        
        class Foo extends Eloquent{

            public function bar(){ return $this->belongsTo("Bar"); }
            
            public function buses(){ return $this->hasMany("Bus"); }
        }
        
        class Bar extends Eloquent{
            public function foos(){ return $this->hasMany("Foo"); }
        }
        
        class Bus extends Eloquent{
            public function foo(){ return $this->belongsTo("Foo"); }
        }
        
    -- app/config/packages/breadam/emberize/config.php --
    
    return array(
        
		"mode" => null,

        "identifier" => array(
            "key" => "id",
            "value" => "public_key"
        ),
        
        "resources" => array(
        
            "foo" => array(
                "fields" => array(
					"name",
					"bar:sideload",
					"buses:"embed"
				)
            ),
            
            "bar" => array(
                
                "identifier" => array(
                    "value" => "bar_specific_public_key"
                ),
                
                "fields" => array(
					"name",
					"foos:links"
				)
            ),
            
            "bus" => array(
                "fields" => array(
					"name",
					"foo:sideload"
				)
            )
        )
    )

    -- Basic usage  --
        
        Route::get('/', function(){
        
	        $foo = Foo::find(1);
	        
            return Emberize::make($foo); 
        });
        
    -- Update fields. Change will persist until the end of request. --
    
        Route::get('/', function(){
        
	        $foo = Foo::find(1);
	        
	        Emberize::fields(array(
	            "foo" => array(
	                "exclude" => "bar"
	            )
	        ));
	        
            return Emberize::make($foo); 
        });
    
    -- Update fields.     --
    
        Route::get('/', function(){
                    
	        $foo = Foo::find(1);
	        
	        Emberize::fields(array(
	           "bar" => array(
	               "exclude" => array("foos")
	           )
	        ));
	        
	        if($someCondition){
	        
	            return Emberize::make($foo,array(
	                "foo" => array(
	                    "exclude" => array("buses")
	                )
	            )); 
	            
	        }else{
	            return Emberize::make($foo,array(
	                "foo" => array(
	                    "exclude" => array("name")
	                ),
	                "buses" => array(
	                    "exclude" => array("foos")
	                )
	           );
	        }
        });
    

### Configuration
        
#### mode: 

    Set default mode. If "null", Emberize will prepare only primary keys of relationships    

    value: null,sideload,embed,link
    default: null
    
#### identifier:
    
	If set, Emberize will use "key" as the primary key name and "value" attribute as primary key value. If not set, Emberize will use $model->getKeyName() and $model->getKey()   
    
    value: 	

		"identifier" => array(
        	"key" => string,
        	"value" => string
    	)

	default:

		"identifier" => array(
        	"key" => $model->getKeyName(),
        	"value" => $model->getKey()
    	)
        
#### resources: 

	"resources" => array(
		
		"resource_1" => array(
			"identifier" => array( "key" => "...","value" => "..."),
			"fields" => array(
				"attribute_1",
				"relationship_1:mode"
			)
		),
		"resource_2" => array(...)
	)

	// Missing

#### resolver:    
	
	// Missing

	value: A class name implementing "Breadam\Emberize\ResourceNameResolverInterface"
	
	value: "Breadam\Emberize\DefaultResourceNameResolver"   
    
    
### Methods
	
#### Emberize::make([$model|$collection],array $fields = null)
	
	'$fields': will be merged with fields defined in config and with Emberize::fields(...).
	
#### Emberize::fields(array $fields,$merge = false)
	
	'$fields': will be merged with fields defined in config. 
	 
	usage: 
	
		Emberize::fields(array(
      "model_name_1" => array(
				"include" => array("field_name_1","field_name_2",...)
				"exclude" => array("field_name_1","field_name_2",...)
       ),
			"model_name_2" => array(
				"include" => array("field_name_1","field_name_2",...)
				"exclude" => array("field_name_1","field_name_2",...)
      )
		));

