const path = require('path');

module.exports = {
  entry: {
    'js/youcan-pay.min.js': './assets/js/youcan-pay.js',
    'js/youcanpay-admin.min.js': './assets/js/youcanpay-admin.js',
  },
  output: {
    path: path.resolve(__dirname, 'assets'),
    filename: '[name]'
  }
};
