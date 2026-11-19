<?php

namespace Pharaonic\Laravel\Translatable\Exceptions;

use LogicException;

/**
 * The query cannot be compiled correctly for the strategy or database.
 */
final class UnsupportedTranslationQueryException extends LogicException implements TranslatableException {}
