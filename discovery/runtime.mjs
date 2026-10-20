import {fileURLToPath} from 'node:url';
import {join} from 'node:path';

export function runtime(local, env = process.env) {
  const root = fileURLToPath(new URL('../', import.meta.url));
  return {
    rawDirectory: local ? join(root, 'raw') : '/raw',
    launchOptions: {
      executablePath: env.CHROMIUM_PATH ?? (local
        ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
        : '/usr/bin/chromium'),
      headless: !local,
      ...(local ? {defaultViewport:null} : {}),
      args: local ? [] : ['--no-sandbox','--disable-dev-shm-usage']
    },
    collection(id) {
      const args = ['matches:collect', id, `--competition=${env.COMPETITION_ID ?? 'virtual-football'}`];
      return local
        ? {command:'docker', args:['compose','-f',join(root,'engine/compose.yaml'),
            'run','--rm','-T','app',...args], cwd:root}
        : {command:'php', args:['/app/artisan',...args], cwd:'/app'};
    }
  };
}
