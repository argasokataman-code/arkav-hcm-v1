const seed = Date.now();

function yyyyMmDd(daysFromToday = 0) {
  const date = new Date();
  date.setDate(date.getDate() + daysFromToday);
  return date.toISOString().slice(0, 10);
}

export const employeeFixtures = {
  create: {
    fullName: `FE Auto Employee ${seed}`,
    email: `fe.employee.${seed}@example.com`,
    password: "StrongPass1",
    phone: `08${String(seed).slice(-10)}`,
    nik: `3174${String(seed).padStart(12, "0").slice(-12)}`,
    placeOfBirth: "Yogyakarta",
    dateOfBirth: "1996-07-21",
    gender: "male",
    maritalStatus: "single",
    religion: "Islam",
    team: "QA Automation",
    employeeType: "permanent",
    baseSalary: "7500000",
    fixedAllowance: "500000",
    salaryType: "monthly",
    contractType: "permanent",
    contractStartDate: yyyyMmDd(-7),
    contractStatus: "active",
    bankAccountNo: `12345${String(seed).slice(-8)}`,
    bankAccountHolderName: `FE AUTO ${seed}`,
    emergencyName: "QA Emergency",
    emergencyRelationship: "Sibling",
    emergencyPhone: "081234567890",
  },
  update: {
    team: `QA Updated ${seed}`,
    employmentStatus: "inactive",
  },
};
