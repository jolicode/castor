<?php

namespace Castor\Exception;

/**
 * Thrown by llm() when no LLM CLI is available, or when the CLI could not
 * answer.
 */
class LlmException extends \RuntimeException
{
}
