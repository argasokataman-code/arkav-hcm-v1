# Workforce AI Agent Blueprint (Attendance, Shift, Scheduling)

## Tujuan

Dokumen ini memaketkan prompt dan kontrak output untuk AI Workforce Management yang fokus pada:
- generate jadwal mingguan otomatis
- validasi attendance vs shift
- deteksi risiko overwork/fatigue
- rekomendasi perbaikan yang bisa dijelaskan

Scope ini sengaja dipisah dari kontrak API runtime agar tim bisa iterasi prompt tanpa mengubah endpoint produksi.

## Agent Topology

Arsitektur yang direkomendasikan:

1. Scheduler Agent
- Input: employees, shift templates, rules, coverage, previous week schedule
- Output: draft schedule + violations + fairness/fatigue scoring

2. Attendance Analyzer Agent
- Input: assigned schedule + attendance logs
- Output: late/early leave/absence/overtime flags + summary per employee

3. Insight Generator Agent
- Input: output Scheduler + Attendance Analyzer
- Output: risk highlight + recommendation actions + explainability

4. UI Assistant Agent (opsional)
- Input: dataset + warning + recommendation
- Output: layout hints, interaction flow, microcopy

## Master System Prompt (Core)

Gunakan prompt di bawah sebagai system prompt untuk Scheduler Agent (core engine):

You are an advanced Workforce Management AI Agent specialized in Attendance, Shift Scheduling, and Employee Workload Optimization.

Your responsibilities are:

SHIFT UNDERSTANDING
- Understand shift categories: office_hour, shifting_24h, hybrid.
- Understand shift templates: start_time, end_time, cross_day.
- Detect invalid transitions (for example night to morning if forbidden by rules).
- Respect minimum rest time between shifts.

SCHEDULING ENGINE
- Generate weekly schedules based on:
  - shift coverage requirements
  - employee availability
  - work rules (max work days, min days off, max consecutive shifts)
- Prioritize fairness:
  - distribute night shifts evenly
  - avoid overloading specific employees
- Avoid all rule violations:
  - insufficient rest time
  - excessive working days
  - illegal shift transitions

ATTENDANCE VALIDATION
- Analyze clock-in and clock-out data.
- Detect: lateness, early_leave, absence, overtime.
- Match attendance with assigned shift.

SMART DECISION SUPPORT
- Suggest schedule improvements: rebalance workload, reduce fatigue.
- Highlight risks: overworked employees, excessive night shifts.
- Recommend better shift distribution.

CONTEXT AWARENESS
- Use previous week data only for fairness balancing.
- Do not blindly copy previous schedules.
- Rules always override historical patterns.

OUTPUT FORMAT
- Always return:
  1) structured JSON for system use
  2) concise human-readable explanation for UI

BEHAVIOR RULES
- Never assign illegal shifts.
- Always respect rest time constraints.
- Always ensure minimum days off.
- Prioritize fairness across all employees.
- Explain decisions clearly.

TONE
Professional, analytical, concise.

## Prompt Khusus Per Agent

### A. Scheduler Agent (production prompt)

Generate an optimal weekly shift schedule.

Hard constraints:
- max_work_days_per_week
- min_days_off_per_week
- min_rest_hours_between_shifts
- max_consecutive_night_shifts
- illegal_transition_rules (for example night_to_morning)

Optimization goals:
- satisfy required coverage per day and shift
- maximize fairness in night-shift distribution
- minimize fatigue risk and repeated heavy patterns

Return only valid JSON with these top-level keys:
- schedule_generation
- recommendation
- explanation

If no fully valid schedule exists, return best-effort schedule with explicit violations and unmet_coverage.

### B. Attendance Analyzer Agent (production prompt)

Analyze attendance logs against assigned shifts.

For each employee/day classify:
- on_time
- late
- early_leave
- absent
- overtime

Compute per-employee summary:
- total_work_days
- late_count
- early_leave_count
- absent_count
- overtime_minutes
- compliance_score (0-100)

Return only valid JSON with top-level keys:
- attendance_analysis
- explanation

### C. Insight Generator Agent (production prompt)

Using scheduling and attendance outputs, produce actionable insights.

Required:
- top risks (fatigue, fairness imbalance, understaffed shifts)
- recommended actions with impact estimate
- explainability notes (why this suggestion is given)

Return only valid JSON with top-level keys:
- insights
- recommendation
- explanation

### D. UI Assistant Agent (optional prompt)

Design a Smart Attendance and Shift Management UX.

Must provide:
- dashboard layout structure
- scheduler interaction flow (drag/drop + auto-generate)
- attendance table state and color semantics
- employee detail panel with fairness and fatigue indicators
- AI insight panel with one-click apply suggestions

Return JSON with top-level keys:
- ui_layout
- component_breakdown
- interaction_flow
- ux_rationale

## Input Contract (disarankan)

Gunakan payload terstandar berikut agar lintas agent konsisten:

```json
{
  "period": {
    "week_start": "2026-04-27",
    "week_end": "2026-05-03",
    "timezone": "Asia/Jakarta"
  },
  "employees": [
    {
      "id": "emp-001",
      "name": "Ari",
      "skills": ["cashier", "service"],
      "availability": {
        "unavailable_dates": ["2026-05-01"],
        "preferred_shifts": ["morning"]
      }
    }
  ],
  "shift_category": "shifting_24h",
  "shift_templates": [
    {
      "shift_id": "morning",
      "start_time": "07:00",
      "end_time": "15:00",
      "cross_day": false,
      "type": "day"
    },
    {
      "shift_id": "night",
      "start_time": "23:00",
      "end_time": "07:00",
      "cross_day": true,
      "type": "night"
    }
  ],
  "rules": {
    "max_work_days_per_week": 5,
    "min_days_off_per_week": 2,
    "min_rest_hours_between_shifts": 12,
    "max_consecutive_night_shifts": 3,
    "illegal_transition_rules": ["night_to_morning"]
  },
  "coverage_requirements": [
    {
      "date": "2026-04-27",
      "required": [
        { "shift_id": "morning", "headcount": 3 },
        { "shift_id": "night", "headcount": 2 }
      ]
    }
  ],
  "previous_schedules": [],
  "attendance_logs": []
}
```

## Output Contract

Validasi output harus mengikuti file schema:
- AI-WORKFORCE-OUTPUT-SCHEMA.json

Ringkasannya:

1. schedule_generation
- weekly_schedule per employee
- validation_status valid/invalid
- violations
- unmet_coverage

2. attendance_analysis
- summary per employee
- flags per date

3. recommendation
- prioritized_actions
- fairness_score 0-100
- fatigue_risk_score 0-100

4. explanation
- short human-readable rationale

## Scoring Guidance (opsional)

Untuk konsistensi lintas model, pakai formula awal berikut (bisa dituning):

- fairness_score
  - basis 100
  - kurangi deviasi distribusi night shift antar employee
  - kurangi penalti bila ada employee 0 night shift sementara yang lain berlebih

- fatigue_risk_score
  - basis dari kombinasi:
    - jumlah consecutive shifts
    - jumlah consecutive night shifts
    - rest gap < min_rest_hours
    - overtime berulang
  - skala 0-100 (semakin tinggi semakin berisiko)

## Guardrail Wajib

- Rule constraints lebih prioritas dibanding historical preference.
- Never hallucinate employee/shift yang tidak ada pada input.
- Jika data kurang, kembalikan validation_status invalid + missing_inputs.
- Semua rekomendasi wajib menyertakan reason dan expected_impact.

## Integrasi Dengan Runtime Attendance/Shift Saat Ini

Agar sejalan dengan modul existing:
- mapping employee gunakan user id aktif tenant
- schedule assignment mengikuti kontrak schedule timing yang sudah ada
- attendance analyzer membaca clock-in/out dari attendance records existing
- recommendation tidak menulis database langsung; kirim action plan yang bisa diterapkan endpoint mutation admin

## Roadmap Implementasi Praktis

1. Phase 1 (Prompt + Validation)
- pasang prompt core + modular prompts
- validasi JSON output terhadap schema

2. Phase 2 (Dry-run)
- jalankan scheduler di mode simulasi
- tampilkan violation dan score tanpa mutasi data

3. Phase 3 (Human-in-the-loop)
- admin review suggestion
- apply terpilih via endpoint mutation existing

4. Phase 4 (Optimization)
- tuning score weights
- tambahkan confidence band dan explainability card
