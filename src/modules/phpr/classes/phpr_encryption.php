<?php

/*
 * Mcrypt helper to help resolve compatibility issues when upgrading older PHP installs
 */

interface Phpr_Encryption
{

    public function encrypt($data, $key = null);

    public function decrypt($data, $key);

    public function get_key_size();

}
