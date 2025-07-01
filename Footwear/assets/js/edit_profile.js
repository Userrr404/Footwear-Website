function preview(inp){
    const file = inp.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => document.getElementById('preview').src = e.target.result;
    reader.readAsDataURL(file);
}

const p = document.getElementById('pass'), s = document.getElementById('strength');
if (p) {
    p.addEventListener('input', () => {
        const score = zxcvbn(p.value).score;
        const texts = ["Very Weak", "Weak", "Fair", "Good", "Strong"];
        s.textContent = "Strength: " + texts[score];
        s.style.color = ["#dc3545", "#fd7e14", "#ffc107", "#28a745", "#007bff"][score];
    });
}