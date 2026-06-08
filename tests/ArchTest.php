<?php

arch('no debugging')
    ->expect(['dd', 'dump', 'var_dump', 'die', 'ray'])
    ->not->toBeUsed();

arch('no direct env calls')
    ->expect('env')
    ->not->toBeUsedIn('Laravel\Doctor');
