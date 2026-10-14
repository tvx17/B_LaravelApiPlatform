import globals from 'globals';

export default [
  { ignores: ['node_modules/**', 'dist/**', 'release/**'] },
  {
    files: ['**/*.js', '**/*.mjs'],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
        chrome: 'readonly',
        LeseklarSettings: 'readonly',
        LeseklarEngine: 'readonly',
        LeseklarDOM: 'readonly',
      },
    },
    rules: {
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-undef': 'error',
      eqeqeq: 'error',
      'no-eval': 'error',
    },
  },
];
