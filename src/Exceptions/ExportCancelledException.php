<?php

namespace Javaabu\Exports\Exceptions;

use RuntimeException;

/**
 * Stops a running export job when its export was cancelled
 */
class ExportCancelledException extends RuntimeException {}
