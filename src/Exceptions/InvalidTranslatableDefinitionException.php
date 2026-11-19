<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use LogicException;

/**
 * A model's translated fields, or its translation options, are declared incorrectly.
 */
final class InvalidTranslatableDefinitionException extends LogicException implements TranslatableException {}
