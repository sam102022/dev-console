<?php

namespace App\model;

enum EnumEnvironment: string
{
    case DEV = 'dev';
    case REC = 'rec';
    case PP = 'pp';
    case PROD = 'prod';

}
