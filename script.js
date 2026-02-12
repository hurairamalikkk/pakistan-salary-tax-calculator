document.addEventListener("DOMContentLoaded", () => {

  const salaryInput = document.getElementById("pstc-salary");
  const calcBtn = document.getElementById("pstc-calc");
  const resultBox = document.getElementById("pstc-result");

  // Format PKR with commas while typing (cursor safe)
  salaryInput.addEventListener("input", function () {
    let value = this.value.replace(/,/g, "");
    if (value === "") return;
    this.value = Number(value).toLocaleString("en-PK");
  });

  calcBtn.addEventListener("click", function () {

    let salary = salaryInput.value.replace(/,/g, "");
    let type   = document.getElementById("pstc-type").value;
    let year   = document.getElementById("pstc-year").value;

    if (!salary || salary <= 0) {
      resultBox.innerHTML = "<p>Please enter a valid salary.</p>";
      return;
    }

    fetch(pstc_ajax.ajax_url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=pstc_calculate_tax&salary=${salary}&type=${type}&year=${year}&nonce=${pstc_ajax.nonce}`,
    })
      .then((r) => r.json())
      .then((d) => {

        if (!d.success) {
          resultBox.innerHTML = "<p>Calculation error. Try again.</p>";
          return;
        }

        let r = d.data;

        resultBox.innerHTML = `
          <p><strong>Annual Salary:</strong> PKR ${r.annual_salary}</p>
          <p><strong>Annual Tax:</strong> PKR ${r.annual_tax}</p>
          <p><strong>Monthly Tax:</strong> PKR ${r.monthly_tax}</p>
          <p><strong>Effective Rate:</strong> ${r.effective_rate}%</p>
        `;
      });
  });

});
