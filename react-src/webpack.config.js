const path = require('path');

module.exports = {
  entry: {
    main: './src/index.js', // Entry point per il Chatbot
    assistantsPage: './src/assistantsPage.js', // Entry point per le Impostazioni Admin
    transcriptionsPage: './src/transcriptionsPage.js', // Entry point per la Creazione di un Assistente
  },
  output: {
    path: path.resolve(__dirname, '../assets/js'),
    filename: '[name].bundle.js',
  },
  module: {
    rules: [
      {
        test: /\.(css|scss)$/,
        use: [
          'style-loader',
          {
            loader: 'css-loader',
            options: {
              importLoaders: 1,
              modules: {
                localIdentName: "[name]__[local]___[hash:base64:6]",
              },
            }
          }
        ],
        include: /\.module\.css$/
      },
      {
        test: /\.(css|scss)$/,
        use: [
          'style-loader',
          'css-loader'
        ],
        exclude: /\.module\.css$/
      },
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react']
          }
        }
      }
    ]
  },
  resolve: {
    extensions: ['.js', '.jsx', '.css'],
  }
};
