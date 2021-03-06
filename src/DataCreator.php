<?php

namespace DependencyManager;



class DataCreator
{
    private $client = null;

    public function __construct($neo4jClient)
    {
        $this->client = $neo4jClient;
        $this->query = null;
    }

    public function testCnx()
    {
       return $this->client->ping();
    }

    public function dropSchema()
    {
        $this->client->sendCypherQuery("MATCH (n) detach delete n");
    }

    public function createNode($nodeName, $nodeType)
    {
        $this->query .= "CREATE (" . $nodeName . ":" . $nodeType . " { name : '" . $nodeName . "' })";
    }

    public function createRelation($fromNode, $relationName, $toNode)
    {
        $this->query .= "CREATE (" . $fromNode . ")-[:" . $relationName . "{type : '" . $relationName . "'}]->(" . $toNode . ")";
    }

    public function createSchema(array $data)
    {

        $this->dropSchema();

        $classCollection = array();
        $interfaceCollection = array();
        $namespaceCollection = array();

        // First iteration on data : extract entities ...
        if ($this->client !== null) {
            foreach ($data as $file) {
                foreach ($file as $class) {
                    if ($class->type === "interface")
                    {
                        array_push($interfaceCollection, $class->classname);
                    } else
                    {
                        array_push($classCollection, $class->classname);
                    }
                    array_push($namespaceCollection, $class->namespace);
                    if (count($class->interfaces))
                    {
                        foreach ($class->interfaces as $interface) {
                            array_push($interfaceCollection, $interface);
                        }
                    }
                }
            }
            $namespaceCollection = array_unique($namespaceCollection);
            $interfaceCollection = array_unique($interfaceCollection);
        }

        // .. and create them
        array_map(function($val){$this->createNode($val,"class");}, $classCollection);
        array_map(function($val){$this->createNode($val,"interface");}, $interfaceCollection);
        array_map(function($val){$this->createNode($val,"namespace");}, $namespaceCollection);
        
        // Second iteration on data : extract relations and create them
        if ($this->client !== null)
        {
            foreach ($data as $file)
            {
                foreach ($file as $class)
                {
                    if(!is_null($class->namespace))
                        $this->createRelation($class->classname, "HAS", $class->namespace);

                    if(!is_null($class->extend))
                        $this->createRelation($class->classname, "EXTENDS", $class->extend);

                    if (count($class->interfaces)) {
                        foreach ($class->interfaces as $interface) {
                            $this->createRelation($class->classname, "IMPLEMENTS", $interface);
                        }
                    }
                    if (count($class->classesInstances)) {
                        foreach ($class->classesInstances as $instanciated) {
                            $this->createRelation($class->classname, "COMPOSES", $instanciated);
                        }
                    }
                    if (count($class->injectedDependencies)) {
                        foreach ($class->injectedDependencies as $injected) {
                            $this->createRelation($injected, "AGGREGATES", $class->classname);
                        }
                    }
                }
            }
        }

        // Go ahead
        $this->client->sendCypherQuery($this->query);
    }
}


