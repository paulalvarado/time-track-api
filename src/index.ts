import { buildApp } from "./app.js";
import { appConfig } from "./config/app.js";

async function main() {
  const app = await buildApp();

  try {
    await app.listen({ port: appConfig.port, host: appConfig.host });
    console.log(`🚀 Server running at http://${appConfig.host}:${appConfig.port}`);
  } catch (err) {
    app.log.error(err);
    process.exit(1);
  }
}

main();
