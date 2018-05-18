<?php

class JWDB {
    private $handle;
    private $columns;
    private $debugOn = false;
    
    // This is the information that is being retrieved from each JW video and can be exported
    // to DFP
    //
    function __construct($dbname = 'test.DB') {
        $this->handle = new SQLite3($dbname);
        $this->columns = array();
        $this->columns["jwId"] = "STRING";
        $this->columns["title"] = "STRING NOT NULL";
        $this->columns["description"] = "STRING";
        $this->columns["published"] = "INTEGER";    // Unix Time
        $this->columns["duration"] = "STRING";
        $this->columns['previewUrl'] = "STRING";
        $this->columns['pageurl'] = "STRING";
        $this->columns['views'] = "INTEGER";
        $this->columns['contentUrl'] = "STRING";
    }
    public function exec($command) {
        $response = false;
        try {
            $response = $this->handle->exec($command);
        } catch (Exception $ex) {
            printf("XXXXXXXX Exception in SQLLITE3::exec: Command: %s. Msg: %s\n", 
                    $command, $ex->getMessage());
        }
        return $response;
    }
    public function query($command) {
        return $this->handle->query($command);        
    }
    public function querySingle($command, $entireRow = false) {
        $response = false;
        try { 
            $response = $this->handle->querySingle($command, $entireRow);        
        } catch (Exception $ex) {
            printf("XXXXXXXX Exception in SQLLITE3::querySingle: Command: %s. Msg: %s\n", 
                    $command, $ex->getMessage());
        }
        return $response;
    }
    public function newTable($tbname) {
        if ($this->debugOn) printf("newTable: Table {$tbname}\n");
        $command = "CREATE TABLE " . $tbname . " ("; 
        $cnt = 0;
        foreach ($this->columns as $name => $type) {
            if ($cnt++ > 0) {
                $command .= ",";
            }
            $command .= $name . " " . $type;
        }
        $command .= ", PRIMARY KEY (jwId));";
        if ($this->debugOn) printf("%s\n", $command);
        $response = $this->exec($command);
        $command = "CREATE INDEX IDX_PAGEURL ON " . $tbname . "(pageurl);";
        if ($this->debugOn) printf("%s\n", $command);        
        $response = $this->exec($command);        
    }
    public function rmTable($tbname) {
        $command = "DROP TABLE " . $tbname;
        if ($this->debugOn) printf("Deleting table $tbname\n");
        return $this->exec($command);
    }
    public function tableExists($tbname) {
        if ($this->debugOn) printf("Checking existence of $tbname\n");
        $command = "SELECT name FROM sqlite_master WHERE type='table' AND name='".$tbname."'";
        $response = $this->querySingle($command);
        if (gettype($response) === 'string' && ($response == $tbname)) return true;
        return false;
    }
    public function keyExists($tbname, $key) {
        $command = "SELECT jwId  FROM " . $tbname . " WHERE jwId='" . $key ."'";  
        $response = $this->querySingle($command);
        if ($response === $key) 
        {
            return true;
        }
        return false;
    }
    public function insertArray($tbname, $row) {
        // Create the INSERT command based on the array
        // while checking to make sure that each column name exists
        $fldnames = '';
        $values = '';
        $cnt = 0;        
        foreach ($row as $name => $value) {
            if (array_key_exists($name, $this->columns)) {
                if ($cnt > 0) {
                    $fldnames .= ', ';
                    $values .= ', ';
                }
                $fldnames .= $name;
                $values .= "'" . SQLite3::escapeString($value) . "'";
            }
            $cnt++;
        }

        // Build the Command
        $command = 'INSERT INTO ' . $tbname . '(' . $fldnames . ')' .
                ' VALUES (' . $values . ')';
        if ($this->debugOn) printf("insertArray: Command: %s\n", $command);
        return $this->exec($command);
                
    }
    
    public function close() {
        $this->handle->close();
    }
    public function updateRow($tbname, $key, $data) {
        /*
         * UPDATE Customers SET ContactName = 'Alfred Schmidt', City= 'Frankfurt' WHERE CustomerID = 1;
         */
        $command = "UPDATE " . $tbname . " SET ";
        $cnt = 0;
        foreach ($data as $name => $value) {
            if ($cnt++ > 0) $command .= ",";
            $command .= $name . "='" . SQLite3::escapeString($value) . "'";
        }
        $command.= " WHERE jwId = '" . $key . "';";
        if ($this->debugOn) printf("updateRow: %s\n", $command);
        return $this->exec($command);
    }
    public function getLatestCreated($tbname) {
        // Find the latest date in the Created field 
        //
        $result = $this->querySingle('SELECT max(recCreated) from ' . $tbname);
        var_dump($result);
        return $result;
    }
    public function lastInsertRowID() {
        return $this->handle->lastInsertRowID();
    }
    public function getNumRows($tblname) {
        $command = 'SELECT count(jwId) from ' . $tblname;
        $result = $this->querySingle($command);
        return $result;
    }
}

