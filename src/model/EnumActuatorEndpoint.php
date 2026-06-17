<?php

namespace App\model;

enum EnumActuatorEndpoint: string
{
    case HEALTH = 'health';
    case INFO = 'info';
    case ENV = 'env';
    case CONFIGPROPS = 'configprops';
    case CONDITIONS = 'conditions';
    case METRICS = 'metrics';
    case MAPPINGS = 'mappings';

}
