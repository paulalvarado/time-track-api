import { geminiConfig } from "../config/gemini.js";

export type SuggestedEntry = {
  date: string;    // YYYY-MM-DD
  concept: string; // descripción de la tarea
  hours: number;   // cantidad de horas
};

const SYSTEM_PROMPT = `Eres un asistente experto en registrar partes de horas en Odoo.
El usuario te dictará una nota de audio describiendo el trabajo que realizó.
Debes extraer la información y responder ÚNICAMENTE con un JSON válido con este esquema:

{
  "entries": [
    {
      "date": "YYYY-MM-DD",
      "concept": "Descripción clara y concisa del trabajo realizado",
      "hours": 2.5
    }
  ]
}

Reglas:
- Si el usuario menciona una fecha específica, úsala. Si no, usa la fecha actual.
- Si menciona varias tareas o días, crea una entrada por cada una.
- Si no menciona horas específicas, asume 1 hora por entrada.
- Responde SOLO con el JSON, sin texto adicional ni markdown.`;

/**
 * Envía audio a Gemini y obtiene sugerencias estructuradas de partes de horas.
 */
export async function transcribeTimesheetAudio(
  audioBase64: string,
  mimeType: string,
  apiKey: string,
): Promise<SuggestedEntry[]> {
  if (!apiKey) throw new Error("GEMINI_API_KEY not configured");

  const { model } = geminiConfig;
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
  const prompt = `Hoy es ${today}. ${SYSTEM_PROMPT}`;

  const body = {
    contents: [
      {
        role: "user",
        parts: [
          { text: prompt },
          {
            inlineData: {
              mimeType,
              data: audioBase64,
            },
          },
        ],
      },
    ],
    generationConfig: {
      temperature: 0.1,
      maxOutputTokens: 1024,
    },
  };

  const res = await fetch(
    `https://generativelanguage.googleapis.com/v1beta/models/${model}:generateContent?key=${apiKey}`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
  );

  if (!res.ok) {
    const errorText = await res.text();
    throw new Error(`Gemini API error (${res.status}): ${errorText}`);
  }

  const data = await res.json();
  const text = data?.candidates?.[0]?.content?.parts?.[0]?.text || "";

  // Extraer JSON de la respuesta (por si viene con markdown ```json ... ```)
  const jsonMatch = text.match(/```(?:json)?\s*([\s\S]*?)```/) || text.match(/{[\s\S]*}/);
  const jsonStr = jsonMatch ? jsonMatch[1] || jsonMatch[0] : text;

  try {
    const parsed = JSON.parse(jsonStr.trim());
    const entries: SuggestedEntry[] = parsed.entries || parsed;
    return entries.map((e: any) => {
      // Si la fecha viene del AI, asegurar que no se interprete como UTC
      let date = e.date || today;
      if (date && date.includes("-")) {
        const parts = date.split("-");
        if (parts.length === 3) {
          const y = parseInt(parts[0]), m = parseInt(parts[1]), d = parseInt(parts[2]);
          if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
            date = `${y}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
          }
        }
      }
      return {
        date,
        concept: e.concept || e.description || e.name || "",
        hours: Math.max(0, parseFloat(String(e.hours || 1))),
      };
    });
  } catch {
    throw new Error(`Failed to parse Gemini response: ${text}`);
  }
}
