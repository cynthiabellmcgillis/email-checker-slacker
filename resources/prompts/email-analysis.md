# Email Analysis Request

Analyze the following marketing email against the brand guidelines provided below.

## Email Content

**Subject Line:** {{SUBJECT}}

**Preview Text:** {{PREVIEW}}

**Body Content:**
{{BODY}}

---

## Brand Guidelines

{{GUIDELINES}}

---

## Analysis Required

Analyze this email against the brand guidelines above and return a JSON response with the following structure:

```json
{
  "verdict": "ship" | "needs_fixes" | "do_not_ship",
  "issues": [
    {
      "severity": "critical" | "warning" | "suggestion",
      "category": "subject" | "preview" | "body" | "cta" | "tone" | "links" | "ps",
      "problem": "Brief description of the issue",
      "fix": "Specific action to take"
    }
  ],
  "summary": "One sentence overall assessment"
}
```

### Verdict Guidelines

- **ship**: Email meets all guidelines, no critical issues, minor suggestions only
- **needs_fixes**: Email has warnings or issues that should be addressed before sending
- **do_not_ship**: Email has critical problems that must be fixed

### Issue Categories

Use these categories to classify issues based on the brand guidelines above:

- **subject**: Subject line issues
- **preview**: Preview text issues
- **body**: Body content and structure issues
- **cta**: Call to action issues
- **tone**: Tone, style, greetings, closings, product naming
- **links**: Forbidden internal links (Notion, Slack, Google Drive)
- **ps**: PS statement (optional, not required)

### Severity Guidelines

- **critical**: Violates mandatory guidelines marked in the brand guidelines
- **warning**: Violates recommended best practices
- **suggestion**: Minor optimizations not explicitly in guidelines

IMPORTANT: Return ONLY the JSON object, no additional text or markdown code blocks.
