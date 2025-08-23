const defaultConfig = require('@wordpress/prettier-config');

module.exports = {
	...defaultConfig,
	plugins: [...(defaultConfig.plugins || []), '@prettier/plugin-php'],
	overrides: [
		...(defaultConfig.overrides || []),
		{
			files: '*.php',
			options: { singleQuote: true, printWidth: 200 },
		},
	],
};
