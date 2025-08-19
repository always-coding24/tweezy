<?php
$mysqli = new mysqli(
    'localhost',
    'root',
    '',
    'chatapp'
);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$pdo = new PDO("mysql:host=localhost;dbname=chatapp", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
session_start();


function alert($content, $title = "") {
    echo '
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
          title: "' . addslashes($title) . '",
          text: "' . addslashes($content) . '",
          icon: "' . $title . '"
        });
      });
    </script>
    ';
  }

function isEmail($email){
    if(preg_match('/^([*+!.&#$�\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,45})$/i', $email)){
        return $email;
    }else{
        return FALSE;
    }
}
function wrapLinks($text)
{
    // Match URLs
    $urlPattern = '/(https?:\/\/[^\s]+|www\.[^\s]+)/i';
    preg_match_all($urlPattern, $text, $urls);

    // Match phone numbers (11 digits) with various formats
    $phonePattern = '/(\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|\(\d{3}\)\s?\d{3}[-.\s]?\d{4}\b|\b\d{11}\b)/';
    preg_match_all($phonePattern, $text, $phones);

    // Match email addresses
    $emailPattern = '/[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}/';
    preg_match_all($emailPattern, $text, $emails);

    // Replace URLs with <a> tags
    foreach ($urls[0] as $url) {
        if (!preg_match('/^https?:\/\//', $url)) {
            $urlWithScheme = 'http://' . $url;
        } else {
            $urlWithScheme = $url;
        }
        $text = str_replace($url, '<a href="' . $urlWithScheme . '" target="_blank">' . $url . '</a>', $text);
    }

    // Replace phone numbers with <a> tags
    foreach ($phones[0] as $phone) {
        $number = preg_replace('/\D/', '', $phone);
        $text = str_replace($phone, '<a href="tel:' . $number . '">' . $phone . '</a>', $text);
    }

    // Replace email addresses with <a> tags
    foreach ($emails[0] as $email) {
        $text = str_replace($email, '<a href="mailto:' . $email . '">' . $email . '</a>', $text);
    }

    return $text;
}

// Example usage


class User {
    private $pdo;
    private $table = 'users';

    private $attributes = [];
    private $primaryKey = 'id';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Dynamically set and get attributes
    public function __set($name, $value) {
        $this->attributes[$name] = $value;
    }

    public function __get($name) {
        return $this->attributes[$name] ?? null;
    }

    // Create a new user
    public function create(array $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($key) => ":$key", array_keys($data)));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute($data)) {
            $this->attributes[$this->primaryKey] = $this->pdo->lastInsertId();
            return $this->findById($this->attributes[$this->primaryKey]);
        }

        throw new Exception('User creation failed.');
    }

    // Update the user
    public function update() {
        $set = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($this->attributes)));
        $sql = "UPDATE {$this->table} SET $set WHERE {$this->primaryKey} = :{$this->primaryKey}";

        $stmt = $this->pdo->prepare($sql);

        if ($stmt->execute($this->attributes)) {
            return true;
        }

        throw new Exception('User update failed.');
    }

    // Find a user by ID
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $this->attributes = $user;
            return $this;
        }

        throw new Exception('User not found.');
    }

    // Find all users with optional filters
    public function findAll(array $filters = []) {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($filters)) {
            $conditions = implode(' AND ', array_map(fn($key) => "$key = :$key", array_keys($filters)));
            $sql .= " WHERE $conditions";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Delete the user by ID
    public function delete() {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);

        if ($stmt->execute(['id' => $this->attributes[$this->primaryKey]])) {
            return true;
        }

        throw new Exception('User deletion failed.');
    }

    // Query Builder for complex queries
    public function where($column, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE $column = :value";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['value' => $value]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Custom query execution
    public function customQuery($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


function is_emoji($string) {
    $pattern = '/^\p{So}$/u'; // Matches a single emoji
    return preg_match($pattern, $string) === 1;
}

class DatabaseTable {
    private $pdo;
    private $table;
    private $primaryKey;

    public function __construct($pdo, $table, $primaryKey = 'id') {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    // Create a new record
    public function create(array $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($key) => ":$key", array_keys($data)));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute($data)) {
            return $this->pdo->lastInsertId();
        }

        throw new Exception("Failed to create record in {$this->table}.");
    }

    // Update a record by primary key
    public function update($id, array $data) {
        $set = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($data)));
        $data[$this->primaryKey] = $id;

        $sql = "UPDATE {$this->table} SET $set WHERE {$this->primaryKey} = :{$this->primaryKey}";
        $stmt = $this->pdo->prepare($sql);

        if ($stmt->execute($data)) {
            return true;
        }

        throw new Exception("Failed to update record in {$this->table} with ID $id.");
    }

    // Find a record by primary key
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Find all records with optional filters
    public function findAll(array $filters = []) {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($filters)) {
            $conditions = implode(' AND ', array_map(fn($key) => "$key = :$key", array_keys($filters)));
            $sql .= " WHERE $conditions";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Delete a record by primary key
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);

        if ($stmt->execute(['id' => $id])) {
            return true;
        }

        throw new Exception("Failed to delete record in {$this->table} with ID $id.");
    }

    // Custom query execution
    public function customQuery($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


$User = new User($pdo);
function run($query) {
    global $pdo;

    try {
        // Prepare the query
        $stmt = $pdo->prepare($query);
        
        // Execute the query
        $stmt->execute();
    } catch (PDOException $e) {
        // Handle any errors
        echo 'Query failed: ' . $e->getMessage();
    }
}