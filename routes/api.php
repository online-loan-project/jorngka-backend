<?php

foreach (glob(__DIR__ . '/api/*.php') as $filename) {
    require_once $filename;
}
