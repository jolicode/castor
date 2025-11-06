/** @type {import('markdownlint').Rule} */
module.exports = {
    names: ['custom.admonition'],
    description: 'GitHub-style admonitions format',
    tags: ['admonition', 'style'],
    function: function rule(params, onError) {
        const validTypes = ['NOTE', 'TIP', 'IMPORTANT', 'WARNING', 'CAUTION'];

        params.tokens.forEach((token, index) => {
            if ('blockquote_open' !== token.type) return;

            const lines = token.map ? params.lines.slice(token.map[0], token.map[1]) : [];
            const firstLine = lines[0]?.trim();

            if (!firstLine?.startsWith('>')) return;

            if (firstLine.startsWith('> [!')) {
                const match = firstLine.match(/^> \[!(\w+)\]/);

                if (!match) {
                    onError({
                        lineNumber: token.map[0] + 1,
                        detail: `Malformed admonition: "${firstLine}". Expected format: "> [!TYPE]" where TYPE ∈ {${validTypes.join(', ')}}.`,
                        context: token.line,
                    });

                    return;
                }

                const type = match[1];
                if (!validTypes.includes(type)) {
                    onError({
                        lineNumber: token.map[0] + 1,
                        detail: `Invalid admonition type: "${type}". Allowed: ${validTypes.join(', ')}.`,
                        context: token.line,
                    });
                }
            } else if (firstLine.startsWith('>[!')) {
                onError({
                    lineNumber: token.map[0] + 1,
                    detail: `Missing space between ">" and "[".`,
                    context: token.line,
                });
            } else if (firstLine.match(/^>\s*\[!\w*/)) {
                onError({
                    lineNumber: token.map[0] + 1,
                    detail: `Suspicious admonition syntax: "${firstLine}". Expected "> [!NOTE]" or another valid type.`,
                    context: token.line,
                });
            }
        });
    },
};
