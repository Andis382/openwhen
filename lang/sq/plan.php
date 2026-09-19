<?php

/*
 * Pse një ndalesë është aty ku është në listë.
 *
 * Shoferi që nuk e sheh arsyen, nuk i beson renditjes; dhe ai që nuk i beson
 * renditjes, ndjek atë të vjetrën dhe nuk prek asgjë. Prandaj çdo ndalesë e
 * zhvendosur thotë pse, dhe çdo ndalesë e lënë në vend e thotë edhe atë —
 * përfshirë, haptas, kur arsyeja është "ende nuk e dimë".
 */

return [
    'reason' => [
        'window'    => 'u zhvendos aty ku zakonisht është hapur',
        'unchanged' => 'tashmë në vend të mirë',
        'unknown'   => 'ende pa mjaft vizita — u la aty ku e kishit',
    ],

    'rule' => [
        'not_before'  => 'rrallë hapur para :time',
        'not_after'   => 'rrallë hapur pas :time',
        'shut_around' => 'shpesh e mbyllur rreth :time',
    ],
];
