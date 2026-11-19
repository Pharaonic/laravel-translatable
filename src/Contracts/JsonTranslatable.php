<?php

namespace Pharaonic\Laravel\Translatable\Contracts;

/**
 * Marker: the model always uses the "json" translation strategy, whatever
 * config("translatable.strategy") says. Implement at most one marker.
 */
interface JsonTranslatable
{
}
