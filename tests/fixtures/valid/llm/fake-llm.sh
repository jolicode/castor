#!/bin/sh
# A fake LLM CLI: it reads the prompt on its standard input and prints an
# answer on its standard output, like the real ones do.
printf 'You asked: %s\nThe answer is 42.\n' "$(cat)"
