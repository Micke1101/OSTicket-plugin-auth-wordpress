<?php

require_once(INCLUDE_DIR.'/class.plugin.php');
require_once(INCLUDE_DIR.'/class.forms.php');


class WordpressConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-wordpress');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'server' => new TextboxField(array(
                'label' => $__('Database server'),
                'hint' => $__('Default domain used in authentication and searches'),
                'default' => 'localhost',
                'configuration' => array('size'=>40, 'length'=>60),
            )),
            'username' => new TextboxField(array(
                'label' => $__('Database user'),
                'hint' => $__('Username for the database'),
                'configuration' => array('size'=>40, 'length'=>120),
            )),
            'password' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'label' => $__('Database password'),
                'hint' => $__("Password for the database"),
                'configuration' => array('size'=>40),
            )),
            'database-name' => new TextboxField(array(
                'label' => $__('Database name'),
                'hint' => $__('What database on the server to look in'),
                'default' => 'wordpress',
                'configuration' => array('size'=>70, 'length'=>120),
            )),
            'table-prefix' => new TextboxField(array(
                'label' => $__('Table prefix'),
                'hint' => $__('What is the prefix of the tables'),
                'default' => 'wp_',
                'configuration' => array('size'=>70, 'length'=>120),
            ))
        );
    }
    
    function pre_save(&$config, &$errors) {
        list($__, $_N) = self::translate();
        global $ost;
        $c = new mysqli($config['server']
            ,$config['username']
            ,$config['password']
            ,$config['database-name']);
        if($c->connect_error){
            $errors['err'] = $__('Unable to connect to wordpress database, error: ' . $c->connect_error);
            return;
        }
        $result = $c->query("SELECT * "
                        . "FROM information_schema.tables "
                        . "WHERE table_schema = '" . $config['database-name'] . "' "
                        . "AND table_name = '" . $config['table-prefix'] . "users' "
                        . "LIMIT 1");

        if ($result->num_rows == 0) {
            $errors['err'] = $__('Unable to find wordpress user table, please check table prefix');
            return;
        }
        $c->close();
        global $msg;
        if (!$errors)
            $msg = $__('Wordpress configuration updated successfully');
        return !$errors;
    }
}

?>
