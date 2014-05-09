<?php

return array(
	
	/*
		sideload: true or false
			
			Sets the default behaviour of Emberize::make(...) when no "$sideload" argument is passed.
		
	*/
	
	"sideload" => true,
	
	/*
		identifier = array(
			"key" => <string to be used as identifier name>, 
			"value" => <attribute name to be used as identifier value> ex: $model->some_attr_name
		);
		
		Sets the global identifier key name and value name to be used as identifier key/value
		
			
			
	*/
	
	"identifier" => array(),
	
	/*
		models => array(
			
			"user" => array(
				
				"identifier" => array(
					"key" => "id",
					"value" => "public_key",
				),
				
				"fields" => array(
				
					"id",			
					"email",
					"public_key",
					"assets", 
					"mate",
				
			)
		);
		
			
			
	*/
	
	"models" => array()
);