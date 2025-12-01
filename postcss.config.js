/**
 * Media Toolkit - PostCSS Configuration
 * 
 * @package Metodo\MediaToolkit
 */

const path = require('path');

module.exports = {
  plugins: {
    'postcss-import': {
      path: [
        path.resolve(__dirname, 'node_modules/metodo-design-system/src'),
        '/Users/michelemarri/Sites/metodo-design-system/src',
      ],
    },
    'autoprefixer': {},
    ...(process.env.NODE_ENV === 'production' ? { 'cssnano': { preset: 'default' } } : {}),
  },
};

