<?php
// list.php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Quản lý File Google Drive</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #ccc; padding: 8px; }
  th { background-color: #f0f0f0; }
  .btn { padding: 5px 10px; margin: 2px; cursor: pointer; }
  input[type=password] { width: 140px; }
</style>
</head>
<body>

<h2>Danh sách File đã upload</h2>

<p>
  Mật khẩu chung (hoặc <strong>admin</strong>): 
  <input type="password" id="commonPassword" />
  <button id="refreshList">Lấy danh sách</button>
</p>

<table id="fileTable">
  <thead>
    <tr>
      <th><input type="checkbox" id="checkAll" /></th>
      <th>Tên file</th>
      <th>Kích thước (MB)</th>
      <th>Ngày upload</th>
      <th>Mật khẩu file</th>
      <th>Hành động</th>
    </tr>
  </thead>
  <tbody>
    <tr><td colspan="6">Chưa có dữ liệu</td></tr>
  </tbody>
</table>

<button id="downloadSelected">Tải file đã chọn</button>
<button id="deleteSelected">Xóa file đã chọn</button>

<script>
const fileTableBody = document.querySelector('#fileTable tbody');
const refreshListBtn = document.getElementById('refreshList');
const commonPasswordInput = document.getElementById('commonPassword');
const checkAllBox = document.getElementById('checkAll');
const downloadSelectedBtn = document.getElementById('downloadSelected');
const deleteSelectedBtn = document.getElementById('deleteSelected');

function formatSize(bytes) {
  return (bytes / (1024*1024)).toFixed(2);
}

async function fetchFileList() {
  const commonPassword = commonPasswordInput.value.trim();
  if (commonPassword === '') {
    alert('Vui lòng nhập mật khẩu chung hoặc admin');
    return;
  }
  const url = `list_files.php?commonPassword=${encodeURIComponent(commonPassword)}`;
  const res = await fetch(url);
  const data = await res.json();
  renderFileList(data);
}

function renderFileList(files) {
  if (!files.length) {
    fileTableBody.innerHTML = '<tr><td colspan="6">Không có file nào hoặc mật khẩu sai</td></tr>';
    return;
  }
  let html = '';
  files.forEach(file => {
    html += `<tr>
      <td><input type="checkbox" class="fileCheckbox" data-id="${file.id}" data-password="${file.password || ''}" /></td>
      <td>${file.name}</td>
      <td>${formatSize(file.size || 0)}</td>
      <td>${file.uploaded_at || ''}</td>
      <td>${file.password ? 'Có mật khẩu' : 'Không'}</td>
      <td>
        <button class="downloadBtn" data-id="${file.id}" data-password="${file.password || ''}">Tải</button>
        <button class="deleteBtn" data-id="${file.id}" data-password="${file.password || ''}">Xóa</button>
      </td>
    </tr>`;
  });
  fileTableBody.innerHTML = html;
}

function confirmPassword(filePassword, commonPassword) {
  return filePassword === '' || filePassword === commonPassword || commonPassword === 'admin';
}

// Bấm "Lấy danh sách"
refreshListBtn.onclick = () => fetchFileList();

checkAllBox.onchange = () => {
  const checked = checkAllBox.checked;
  document.querySelectorAll('.fileCheckbox').forEach(cb => cb.checked = checked);
};

// Tải file đơn
fileTableBody.onclick = e => {
  if (e.target.classList.contains('downloadBtn')) {
    const id = e.target.dataset.id;
    const filePassword = e.target.dataset.password;
    const commonPassword = commonPasswordInput.value.trim();
    
    if (!confirmPassword(filePassword, commonPassword)) {
      alert('File này có mật khẩu, vui lòng nhập đúng mật khẩu chung hoặc admin');
      return;
    }
    
    window.open(`download.php?id=${encodeURIComponent(id)}`, '_blank');
  }
  
  // Xóa file đơn
  if (e.target.classList.contains('deleteBtn')) {
    const id = e.target.dataset.id;
    const filePassword = e.target.dataset.password;
    const commonPassword = commonPasswordInput.value.trim();
    
    if (!confirmPassword(filePassword, commonPassword)) {
      alert('File này có mật khẩu, vui lòng nhập đúng mật khẩu chung hoặc admin');
      return;
    }
    
    if (!confirm('Bạn có chắc muốn xóa file này không?')) return;
    
    fetch('delete.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id})
    }).then(res => res.json()).then(data => {
      if (data.success) {
        alert('Xóa thành công');
        fetchFileList();
      } else {
        alert('Xóa thất bại: ' + data.error);
      }
    }).catch(() => alert('Lỗi mạng hoặc server'));
  }
};

// Tải nhiều file đã chọn
downloadSelectedBtn.onclick = () => {
  const commonPassword = commonPasswordInput.value.trim();
  if (!commonPassword) {
    alert('Vui lòng nhập mật khẩu chung hoặc admin');
    return;
  }
  const checkedBoxes = Array.from(document.querySelectorAll('.fileCheckbox:checked'));
  if (checkedBoxes.length === 0) {
    alert('Vui lòng chọn file để tải');
    return;
  }
  checkedBoxes.forEach(cb => {
    const id = cb.dataset.id;
    const filePassword = cb.dataset.password;
    if (!confirmPassword(filePassword, commonPassword)) {
      alert(`File có ID ${id} có mật khẩu, vui lòng nhập đúng mật khẩu chung hoặc admin`);
      return;
    }
    window.open(`download.php?id=${encodeURIComponent(id)}`, '_blank');
  });
};

// Xóa nhiều file đã chọn
deleteSelectedBtn.onclick = () => {
  const commonPassword = commonPasswordInput.value.trim();
  if (!commonPassword) {
    alert('Vui lòng nhập mật khẩu chung hoặc admin');
    return;
  }
  const checkedBoxes = Array.from(document.querySelectorAll('.fileCheckbox:checked'));
  if (checkedBoxes.length === 0) {
    alert('Vui lòng chọn file để xóa');
    return;
  }
  
  if (!confirm('Bạn có chắc muốn xóa các file đã chọn không?')) return;
  
  let promises = checkedBoxes.map(cb => {
    const id = cb.dataset.id;
    const filePassword = cb.dataset.password;
    if (!confirmPassword(filePassword, commonPassword)) {
      alert(`File có ID ${id} có mật khẩu, vui lòng nhập đúng mật khẩu chung hoặc admin`);
      return Promise.resolve(null);
    }
    return fetch('delete.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id})
    }).then(res => res.json());
  });
  
  Promise.all(promises).then(results => {
    alert('Xử lý xong. Vui lòng tải lại danh sách.');
    fetchFileList();
  });
};
</script>

<p><a href="index.html">Quay lại trang upload</a></p>

</body>
</html>
