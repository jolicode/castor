#!/bin/sh
# A fake LLM CLI: it reads the prompt on its standard input and prints an
# answer on its standard output, like the real ones do. Its first argument, if
# any, is repeated in the answer, to tell which configuration ran it.
printf 'You asked: %s\nThe answer is 42.%s\n' "$(cat)" "${1:+ ($1)}"
