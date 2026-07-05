import { prisma } from "../src/lib/prisma.js";
import { OdooService } from "../src/services/odoo.js";

const TARGET_ODOO_IDS = [1797, 1799]; // entries with wrong userOdooId

async function main() {
  const configs = await prisma.odooConfig.findMany();
  if (configs.length === 0) { console.log("No configs"); return; }
  const config = configs[0];

  const odoo = new OdooService({
    url: config.url, dbName: config.dbName,
    username: config.username, apiKey: config.apiKey,
  });
  await odoo.authenticate();

  for (const tsOdooId of TARGET_ODOO_IDS) {
    const record = await odoo.readRecord("account.analytic.line", tsOdooId, ["id", "employee_id", "user_id"]);
    console.log(`Timesheet ${tsOdooId}:`, JSON.stringify(record));

    if (record?.employee_id) {
      const empId = Array.isArray(record.employee_id) ? record.employee_id[0] : record.employee_id;
      // Try to get user_id from employee
      let newUserId: number | null = null;
      try {
        const empRec = await odoo.readRecord("hr.employee", empId, ["id", "name", "user_id"]);
        if (empRec?.user_id) {
          const linkedUser = Array.isArray(empRec.user_id) ? empRec.user_id[0] : empRec.user_id;
          newUserId = typeof linkedUser === "number" ? linkedUser : null;
        }
        console.log(`  Employee ${empId}: name="${empRec?.name}", user_id=${newUserId}`);
      } catch {}
      // If no linked user, store employee_id directly
      if (!newUserId) newUserId = empId;

      await prisma.syncTimesheet.updateMany({
        where: { odooId: tsOdooId, odooConfigId: config.id },
        data: { userOdooId: newUserId },
      });
      console.log(`  ✅ Updated userOdooId to ${newUserId}`);
    }
  }

  await prisma.$disconnect();
}

main().catch((err) => { console.error(err); process.exit(1); });
