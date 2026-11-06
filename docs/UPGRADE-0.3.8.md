# 0.3.8: Lightweight JSON Generation with PHP Field Normalization

The previous prompt forced the AI model to generate 13 repetitive boilerplate fields for every single section (such as empty hint, simpler, extraexample, feedback, origin strings), ballooning the prompt and response to thousands of tokens and causing Google's API gateway to terminate the connection at 23 seconds with HTTP 503.

### Key Architectural Simplification:
1. **Lightweight AI Generation:**
   - The AI model only generates the essential content: `kind`, `title`, `body`, and section-specific content (`cards` for vocabulary, `practice`/`answer` for reading/writing, `audioscript` for listening/speaking).
   - All boilerplate fields (`example`, `hint`, `simpler`, `extraexample`, `origin`, etc.) are removed from the required AI output.
   - Total generation tokens drop from 3,000+ to **~675 tokens**, allowing the model to finish in **under 8 seconds**.
2. **Server-Side PHP Normalization:**
   - When the JSON response arrives from Gemini, PHP automatically populates any omitted optional fields with valid defaults before passing it to `lesson::validate()`.
   - All Moodle database tables, templates, and student views receive their expected fields with 100% backward compatibility.
3. **Model Default Update:**
   - Updated the model setting to `gemini-3.5-flash`.

