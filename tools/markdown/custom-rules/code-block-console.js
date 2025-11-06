/** @type {import('markdownlint').Rule} */
module.exports = {
    names: ['custom/code-block-language'],
    description: 'Consistent usage of console code blocks',
    tags: ['code', 'style'],
    function: function rule(params, onError) {
        params.tokens
            .filter((token) => token.type === 'fence')
            .forEach((token) => {
                const lang = (token.info || '').trim();
                const lines = token.content.split("\n").filter(Boolean);

                // Rule 1: disallow `shell`
                if ('shell' === lang) {
                    onError({
                        lineNumber: token.map[0] + 1,
                        detail: 'Use "bash" or "console" instead of "shell".',
                        context: token.line,
                    });
                }

                // Rule 2: bash blocks must NOT start with '$'
                if ('bash' === lang && lines.some((line) => line.trim().startsWith('$'))) {
                    onError({
                        lineNumber: token.map[0] + 1,
                        detail: 'Bash code blocks must not include lines starting with "$".',
                        context: lines.find((l) => l.trim().startsWith('$')),
                    });
                }

                // Rule 3: console blocks must start with '$'
                if ('console' === lang && lines.length > 0 && !lines[0].trim().startsWith('$')) {
                    onError({
                        lineNumber: token.map[0] + 1,
                        detail: 'Console code blocks must start with a "$" prompt.',
                        context: lines[0],
                    });
                }

                // Rule 4: console blocks must contain more than just a single '$' line (i.e. must show some output)
                if ('console' === lang) {
                    const commandLines = lines.filter((l) => l.trim().startsWith('$'));
                    const outputLines = lines.filter((l) => !l.trim().startsWith('$') && l.trim() !== '');

                    if (commandLines.length === 1 && outputLines.length === 0) {
                        onError({
                            lineNumber: token.map[0] + 1,
                            detail: 'Console code blocks should show command output. Use "bash" instead if there\'s no output.',
                            context: lines[0],
                        });
                    }
                }
            });
    },
};
