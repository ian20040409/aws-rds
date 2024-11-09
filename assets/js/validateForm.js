function validateForm() {
    var name = document.getElementById("name").value;
    var email = document.getElementById("email").value;
    var message = document.getElementById("message").value;
    var response = grecaptcha.getResponse();
  
    if (name === "" || email === "" || message === "") {
      alert("請填寫所有必填欄位");
      return false;
    }
  
    if (response.length === 0) {
      // 用戶尚未完成驗證，阻止表單提交
      alert('請先完成驗證');
      return false;
    }
  
    // 用戶已完成驗證，允許表單提交
    return true;
  }