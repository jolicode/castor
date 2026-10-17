<?php

namespace Castor\Exception;

/**
 * Thrown by llm_ask() and llm_run() when no LLM CLI is available, when the call is refused,
 * or when the CLI could not answer.
 */
class LlmException extends \RuntimeException
{
}
