#  ! INCOMPLETE !

#  Laravel 4 response generator for Ember

**feedback appreciated**

## Contents

- [Installation](#Installation)
- [Configuration](#Configuration)
- [Usage](#Usage)
	
### Installation

	1. Add the following to your composer.json "require-dev" array 
    	
        "breadam/emberize": "dev-master"

	2. Add the following to your app/config/app.php "providers" array

        "Breadam\Emberize\EmberizeServiceProvider"
	
	3. Update composer
        
        composer update
    
    4. Publish config file 
        
        php artisan config:publish breadam/emberize
    
    5. Edit app/config/packages/breadam/emberize/config.php to your needs

### Quick Example

    -- database --
    
    table foos 
        id
        public_key
        name
        bar_id
    
    table bars 
        id
        bar_specific_public_key
        name
        
    table buses 
        id
        name
        foo_id
    
    -- models --
        
        class Foo extends Eloquent{
            public function bar(){
                return $this->belongsTo("Bar");
            }
            
            public function buses(){
                return $this->hasMany("Bus");
            }
        }
        
        class Bar extends Eloquent{
            public function foos(){
                return $this->hasMany("Foo");
            }
        }
        
        class Bus extends Eloquent{
            public function foo(){
                return $this->belongsTo("Foo");
            }
        }
        
    -- app/config/packages/breadam/emberize/config.php --
    
    return array(
        "sideload" => true,
        
        "identifier" => array(
            "key" => "id",
            "value" => "public_key"
        ),
        
        "models" => array(
        
            "foo" => array(
                "fields" => array("name","bar","buses")
            ),
            
            "bar" => array(
                
                "identifier" => array(
                    "value" => "bar_specific_public_key"
                ),
                
                "fields" => array("name","foos")
                
            )
            
            "bus" => array(
                
                "identifier" => array(
                    "key" => "id"
                ),
                
                "fields" => array("name","foo")
                
            )
        )
    )

    -- basic usage  --
        
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
        
#### sideload: 

    Sets the default behaviour of Emberize::make(...) when no $sideload argument is passed.    

    value: [true|false]
    default: true
    
#### identifier:
        
    array(
        "key" => string
        "value" => string
    )
        
#### models: 
    
    
    
    value:
    
        array(
            "model_name_1" => array(
                "identifier" => array(
                    "key" => string, 
                    "value"=> string
                ),
                "fields" => array("field_name_1","field_name_2",...)
            ),
            "model_name_2" => array(
                "identifier" => array(
                    "key" => string, 
                    "value"=> string
                ),
                "fields" => array("field_name_1","field_name_2",...)
            )
        )
    
    
