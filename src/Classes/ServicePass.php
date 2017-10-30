<?php

namespace Pixelarbeit\Webware\Classes;

class ServicePass
{

    public function __construct($id, $secret) {
        $this->id = $id;
        $this->appSecret = $secret;
    }

    public static function createFromObject($obj)
    {
        $pass = new self($obj->PASSID, $obj->APPID);

        $pass->creationDate = $obj->PDATE;
        $pass->creationTime = $obj->PTIME;

        return $pass;
    }
}
