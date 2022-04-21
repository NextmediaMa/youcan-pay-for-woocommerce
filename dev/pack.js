const JSZip = require('jszip');
const path = require('path');
const fs = require('fs');
const cliSpinners = require('cli-spinners');
const ora = require('ora');

const addFilesFromDirectoryToZip = (directoryPath = '', zip) => {
  const directoryContents = fs.readdirSync(directoryPath, {
    withFileTypes: true,
  });

  directoryContents.forEach(({name}) => {
    const path = `${directoryPath}/${name}`;

    if (fs.statSync(path).isFile()) {
      zip.file(path, fs.readFileSync(path, 'utf-8'));
    }

    if (fs.statSync(path).isDirectory()) {
      addFilesFromDirectoryToZip(path, zip);
    }
  });
};

(async () => {
  try {
    const spinner = ora({
      text: 'Packing plugin...',
      spinner: cliSpinners.material,
    }).start();

    const zip = new JSZip();
    const rootPath = path.resolve(__dirname, '../');
    const fileName = 'youcanpay-for-woocommerce.zip';

    addFilesFromDirectoryToZip(rootPath, zip);

    const zipAsBase64 = await zip.generateAsync({type: 'base64'});

    fs.writeFile(fileName, zipAsBase64, 'base64', function (err) {
      if (err !== null) {
        spinner.fail('Error while packing plugin.');
        console.error(err);

        return;
      }

      spinner.succeed(`Successfully packed plugin.\nPath: ${path.resolve(rootPath, fileName)}`);
    });
  }catch (e){
    console.log('Unknown error while packing');
    console.error(e);
  }
})();
