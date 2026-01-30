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

Analyze this email against the brand guidelines and return a JSON response with the following structure:

```json
{
  "verdict": "ship" | "needs_fixes" | "do_not_ship",
  "confidence": 1-5,
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

### Confidence Scale

- **5**: Very confident in assessment
- **4**: Confident, minor uncertainty
- **3**: Moderately confident
- **2**: Some uncertainty
- **1**: Low confidence, needs human review

### Issue Categories

Evaluate the email for:

1. **subject**: Subject line quality
   - Front-loads benefit?
   - Follows recommended formulas (Problem+Solution, Transformation, Curiosity Gap, Specific Benefit)?
   - Clear and direct?
   - Length appropriate (under 65 chars)?

2. **preview**: Preview text quality
   - Complements subject line?
   - Provides additional context?
   - Utilizes available space?

3. **body**: Body content quality
   - Has quick reward in first 10 seconds (compelling quote, stat, or benefit)?
   - Single clear focus/idea?
   - Follows Hook > Problem > Solution > How-To structure?
   - Specific rather than generic content?

4. **cta**: Call to action analysis
   - Clear and contextual CTAs?
   - Appropriate number of CTAs (1-2 recommended)?

5. **ps**: PS statement
   - Present at end of email?
   - Adds impact if present?

6. **tone**: Tone and style
   - Contractions used appropriately for conversational tone?
   - Product names capitalized consistently?
   - Professional but approachable language?

### Severity Guidelines

- **critical**: Must fix before sending (broken structure, missing CTA, major guideline violations)
- **warning**: Should fix for better results (suboptimal subject, missing PS, minor issues)
- **suggestion**: Nice to have improvements (tone tweaks, minor optimizations)

IMPORTANT: Return ONLY the JSON object, no additional text or markdown code blocks.
