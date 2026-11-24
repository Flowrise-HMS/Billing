<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;

/**
 * Base class for the Billing web controllers (PDFs, receipts, CSV exports,
 * line checkout). The nwidart scaffold actions that rendered placeholder
 * views were removed; subclasses are invokable.
 */
abstract class BillingController extends Controller {}
