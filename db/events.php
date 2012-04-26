<?php

$handlers = array (
    'shib_user_login' => array (
         'handlerfile'      => '/enrol/attributes/lib.php',
         'handlerfunction'  => 'enrol_attributes_plugin::process_enrolments',
         'schedule'         => 'instant'
     )
);