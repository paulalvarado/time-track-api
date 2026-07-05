import https from "https";

// Fetch the kanban color stylesheet from Odoo
const url = "https://erp.web-informatica.com/web/css/032af5e/web_kanban.css";

https.get(url, (res) => {
  let data = "";
  res.on("data", (chunk) => (data += chunk));
  res.on("end", () => {
    const regex = /\.oe_kanban_color_(\d+)\s*\{[^}]*background-color:\s*([^;}]+)/g;
    let match;
    const colors = {};
    while ((match = regex.exec(data)) !== null) {
      colors[parseInt(match[1])] = match[2].trim();
    }

    if (Object.keys(colors).length === 0) {
      console.log("No direct matches, searching raw...");
      // Broader search
      const allColorRules = data.match(/oe_kanban_color_\d+[^}]+}/g);
      if (allColorRules) allColorRules.forEach((r) => console.log(r.trim()));
    } else {
      console.log("Odoo kanban colors found:");
      for (let i = 0; i <= 11; i++) {
        console.log(`  ${i}: ${colors[i] || "not found"}`);
      }
    }
  });
}).on("error", (e) => console.log("Error:", e.message));
