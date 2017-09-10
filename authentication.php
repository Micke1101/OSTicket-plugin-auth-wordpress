<?php
require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
require_once('PasswordHash.php');

function flatten($array) {
    $a = array();
    foreach ($array as $e) {
        if (is_array($e))
            $a = array_merge($a, flatten($e));
        else
            $a[] = $e;
    }
    return $a;
}

function splat($what) {
    return is_array($what) ? flatten($what) : array($what);
}

require_once(INCLUDE_DIR.'class.auth.php');
class WordpressAuthentication {

    var $config;
    var $type = 'staff';

    function __construct($config, $type='staff') {
        $this->config = $config;
        $this->type = $type;
    }
    function getConfig() {
        return $this->config;
    }

    function getConnection($force_reconnect=false) {
        static $connection = null;

        if ($connection && !$force_reconnect)
            return $connection;

        $c = new mysqli($this->getConfig()->get('server')
            ,$this->getConfig()->get('username')
            ,$this->getConfig()->get('password')
            ,$this->getConfig()->get('database-name'));
        if(!$c->connect_error){
            return $c;
        }
    }
    
    function checkPassword($password, $hash){
        $wp_hasher = new PasswordHash(8, TRUE);
        return $wp_hasher->CheckPassword($password, $hash);
    }

    function authenticate($username, $password=null) {
        if (!$password)
            return null;

        if(($c = $this->getConnection())){
			$username = mysqli_real_escape_string($c, $username);
            $result = $c->query("SELECT `user_login`, `user_pass`, `user_email`, `display_name` "
                . "FROM `" . $this->getConfig()->get('table-prefix') . "users` "
                . "WHERE `user_login` = '" . $username . "' OR `user_email` = '" . $username . "'");
            $config = $this->getConfig();
            
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    if($this->checkPassword($password, $row["user_pass"])){
                        $info = array(
                            'email' => $row["user_email"],
                            'name' => $row["display_name"]
                        );
                        return $this->lookupAndSync($username, $info);
                    }
                }
            }
            $c->close();
        }
        return null;
    }

    function lookupAndSync($username, $info) {
        switch ($this->type) {
        case 'staff':
            if (($user = StaffSession::lookup($username)) && $user->getId()) {
                if (!$user instanceof StaffSession) {
                    // osTicket <= v1.9.7 or so
                    $user = new StaffSession($user->getId());
                }
                return $user;
            }
            break;
        case 'client':
            $c = $this->getConnection();

            $acct = false;
            foreach (array($username, $info['email']) as $name) {
                if ($name && ($acct = ClientAccount::lookupByUsername($name)))
                    break;
            }
            if (!$acct)
                return new ClientCreateRequest($this, $username, $info);

            if (($client = new ClientSession(new EndUser($acct->getUser())))
                    && !$client->getId())
                return;

            return $client;
        }
    }
}

class ClientLDAPAuthentication extends UserAuthenticationBackend {
    static $name = /* trans */ "Wordpress";
    static $id = "wordpress.client";

    function __construct($config) {
        $this->_wordpress = new WordpressAuthentication($config, 'client');
    }

    function getName() {
        $config = $this->config;
        list($__, $_N) = $config::translate();
        return $__(static::$name);
    }

    function authenticate($username, $password=false, $errors=array()) {
        $object = $this->_wordpress->authenticate($username, $password);
        if ($object instanceof ClientCreateRequest)
            $object->setBackend($this);
        return $object;
    }
}

class WordpressAuthPlugin extends Plugin {
    var $config_class = 'WordpressConfig';

    function bootstrap() {
        $config = $this->getConfig();
        UserAuthenticationBackend::register(new ClientLDAPAuthentication($config));
    }
}
