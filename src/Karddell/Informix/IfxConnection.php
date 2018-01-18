<?php

namespace Karddell\Informix;

use Illuminate\Database\Connection;
use Karddell\Informix\Query\Processors\IfxProcessor;
use Karddell\Informix\Query\Grammars\IfxGrammar as QueryGrammar;
use Karddell\Informix\Schema\Grammars\IfxGrammar as SchemaGrammar;
use Karddell\Informix\Schema\IfxBuilder as SchemaBuilder;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

class IfxConnection extends Connection
{

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Illuminate\Database\Schema\MySqlBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }
        return new SchemaBuilder($this);
    }


    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\SqlServerProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new IfxProcessor;
    }


    public function prepareBindings(array $bindings){
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => &$value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $value = $value->format($grammar->getDateFormat());
            } elseif ($value === false) {
                $value = 0;
            }
            if(is_string($value)) {
                $value = $this->convertCharset($value);
            }
        }
        
        return $bindings;
    }

    protected function convertCharset($value){

        //IGNORE
        //return iconv($in_encoding, "{$out_encoding}//IGNORE", trim($value));
        return trim(utf8_encode($value));
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if(config("app.debug"))
            Log::debug("query: ".$query." with ".implode(', ', $bindings));
        $results = parent::select($query, $bindings, $useReadPdo);

        if($results){
            if(is_array($results) || is_object($results)){
                foreach($results as &$result){
                    if(is_array($result) || is_object($result)){
                        foreach($result as $key=>&$value){
                            if(is_string($value)){
                                $value = $this->convertCharset($value);
                            }
                        }
                    } else if(is_string($result)) {
                        $result = $this->convertCharset($result);
                    }
                }
            } else if(is_string($results)) {
                $results = $this->convertCharset($results);
            }
        }
        
        return $results;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\SqlServerGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\SqlServerGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }


    public function statement($query, $bindings = [])
    {

        if(config("app.debug"))
            Log::debug("statement: ".$query." with ".implode(', ', $bindings));
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }
            $count = substr_count($query, '?');
            if($count == count($bindings)){
                $bindings = $this->prepareBindings($bindings);
                return $this->getPdo()->prepare($query)->execute($bindings);
            }

            if(count($bindings) % $count > 0)
                throw new \InvalidArgumentException('the driver can not support multi-insert.');

            $mutiBindings = array_chunk($bindings, $count);
            $this->beginTransaction();
            try{
                $pdo = $this->getPdo();
                $stmt = $pdo->prepare($query);

                foreach($mutiBindings as $mutiBinding){
                    $mutiBinding = $this->prepareBindings($mutiBinding);
                    $stmt->execute($mutiBinding);
                }
            }catch(\Exception $e){
                $this->rollBack();
                return false;
            }catch(\Throwable $e){
                $this->rollBack();
                return false;
            }
            $this->commit();

            return true;

        });
    }

    public function affectingStatement($query, $bindings = [])
    {
        if(config("app.debug"))
            Log::debug("affectingStatement: ".$query." with ".implode(', ', $bindings));
        return parent::affectingStatement($query, $bindings);
    }


}