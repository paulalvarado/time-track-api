import { writeFileSync, readdirSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";
import { green, cyan } from "kolorist";

const __dirname = dirname(fileURLToPath(import.meta.url));
const MIGRATIONS_DIR = join(__dirname, "../../migrations");

function parseArgs(): Record<string, string> {
  const args: Record<string, string> = {};
  for (const arg of process.argv.slice(2)) {
    const match = arg.match(/^--(\w+)=(.+)$/);
    if (match) {
      args[match[1]] = match[2];
    }
  }
  return args;
}

function getNextNumber(): number {
  try {
    const files = readdirSync(MIGRATIONS_DIR)
      .filter((f) => f.endsWith(".ts"))
      .sort();
    if (files.length === 0) return 1;
    const last = files[files.length - 1];
    const num = parseInt(last.slice(0, 3), 10);
    return isNaN(num) ? 1 : num + 1;
  } catch {
    return 1;
  }
}

function generateClassName(name: string): string {
  return name
    .split(/[_\s-]+/)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join("");
}

const args = parseArgs();
const name = args.name;
if (!name) {
  console.error("❌ Usage: pnpm migrate:make --name=descripcion");
  process.exit(1);
}

const num = String(getNextNumber()).padStart(3, "0");
const fileName = `${num}_${name}.ts`;
const className = generateClassName(name);

const template = `import { Migration } from "../src/lib/Migration.js";

export default class ${className} extends Migration {
  override async run(): Promise<void> {
    // TODO: define table schema or execute SQL
    // await this.createTable(yourTable);
    // or
    // await this.execute("ALTER TABLE ...");
  }

  override async down(): Promise<void> {
    // TODO: revert what run() did
    // await this.dropTable("YourTable");
    // or
    // await this.execute("ALTER TABLE ... DROP COLUMN ...");
  }
}
`;

writeFileSync(join(MIGRATIONS_DIR, fileName), template, "utf-8");
console.log(green(`✅ Created migration: ${fileName}`));
console.log(cyan(`   ${join(MIGRATIONS_DIR, fileName)}`));
