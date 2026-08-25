# Hồ Sơ Ghi Nhận Điểm Nghẽn Giao Thức RQ3 (Phase 5C RQ3 Protocol Blocker Record)

**Dự án:** Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis  
**Repository:** `D:\Projects\fim-dashboard`  
**Nhánh:** `phase/4-experiments`  
**Trạng thái điểm nghẽn (Blocker Status):** `RESOLVED` (ĐÃ HOÀN TẤT GIẢI TỎA TOÀN DIỆN TẠI PHASE 5C-R1)  
**Bản sửa đổi nộp bị ảnh hưởng lịch sử:** `b6c1573782706af8e4c8a82cfd08dd4b521f7f2c`  
**Bản sửa đổi báo cáo được cập nhật chuẩn:** `69844dfd6e4cf6ffb96baf5c1c4691e3df78a72e` (PHASE_5C_R1_REPORT_REVISION)  
**Bản sửa đổi dẫn xuất Phase-4 chuẩn:** `abb3ada361089b47c7b6aa2dc3e3736d452f0c88` (PHASE_4E_R1_RQ3_DERIVATIVES_READY)  
**Thời gian ghi nhận ban đầu:** 2026-08-21  
**Thời gian giải tỏa hoàn tất:** 2026-08-25  

---

## 1. Lý Do và Tình Trạng Điểm Nghẽn Ban Đầu (Historical Blocker Classification)

Quá trình kiểm toán độc lập phát hiện rằng thực nghiệm đo kiểm trực quan hóa RQ3 lịch sử được thực thi tại bản sửa đổi `6276e0888e0f6ef7e8e676a451b80f7831504130` và đóng băng bằng chứng tại `2f362d4a415ecf57bf3fd60d96a38aeaa579567c` đã tồn tại các điểm lệch giao thức so with phương pháp luận Phase-4A đã được phê duyệt trước đó:
1. Kích thước khung vẽ thực nghiệm được triển khai $800 \times 600\text{ px}$ thay vì chuẩn đóng băng $800 \times 500\text{ px}$.
2. Đường lưới đồ thị bị tắt với 6 vị trí tick thay vì hiển thị đúng 5 đường lưới tuyến tính cố định trên mỗi trục toạ độ.
3. Bộ sinh dữ liệu thử tải sử dụng thuật toán LCG với seed 42 thay vì thuật toán Mulberry32 với seed `0xDEADBEEF`.
4. Dữ liệu thử tải bị phân mảnh thành 4 tệp JSON riêng biệt thay vì 1 tệp `workload_data.json` duy nhất.
5. Ngữ nghĩa cập nhật dữ liệu tại chỗ tái tạo toàn bộ toạ độ điểm thay vì bảo toàn bất biến 50% điểm gốc và dịch chuyển đúng 50% ($y_i \leftarrow (y_i + 0.1) \pmod{1.0}$).
6. Giao thức ổn định giữa các lần chạy sử dụng `double-rAF + setTimeout(16ms)` thay vì chu kỳ giải phóng đối tượng đồ họa, dọn sạch DOM và độ trễ chờ cố định 100 ms.

---

## 2. Lộ Trình Khắc Phục Đã Hoàn Thành (Completed Remediation Roadmap)

1. **Phase 4D-R1 (Hoàn thành):** Phục hồi cấu hình giao thức chuẩn, bộ sinh dữ liệu Mulberry32, tệp `workload_data.json` đơn nhất, các adapter thư viện 800x500 px với 5 đường lưới, và lưu trữ dữ liệu lịch sử vào `experiments/diagnostic/rq3_6276_protocol_deviation/`.
2. **Phase 4D-R2 & 4D-R3 (Hoàn thành & Đã chấp thuận):** Thực thi phiên chạy hình thức thay thế (120 quan sát hoàn tất 100% không lỗi) và ghi nhận hồ sơ chấp thuận chuẩn hoá tại `experiments/evidence/RQ3_REPLACEMENT_ACCEPTANCE.json`. Dữ liệu thô (`9e80833a...`) và tóm tắt (`8628fb95...`) RQ3 thay thế đã chính thức được công nhận.
3. **Phase 4E-R1 (Hoàn thành):** Tái tạo bảng Bảng 3 (`T3`), Hình 5 (`F5`), Hình 6 (`F6`) từ dữ liệu thực nghiệm RQ3 chuẩn hoá mới, làm mới báo cáo bằng chứng `PHASE_4_EMPIRICAL_FINDINGS.md` và đóng băng `phase4_evidence_manifest.json` (`abb3ada361089b47c7b6aa2dc3e3736d452f0c88`).
4. **Phase 5C-R1 (Hoàn thành & Giải tỏa):** 
   - Cập nhật toàn bộ nội dung học thuật, số liệu Bảng 3, phân tích chi tiết và thảo luận RQ3 trong `docs/report/MIDTERM_REPORT_FINAL.md` (`69844dfd6e4cf6ffb96baf5c1c4691e3df78a72e`).
   - Cập nhật mã băm bằng chứng chuẩn trong `docs/report/SUBMISSION_CHECKLIST.md`.
   - Tái lập và đóng băng `docs/report/REPORT_RELEASE_MANIFEST.json` với nguồn gốc minh bạch 100%.
   - Toàn bộ suite kiểm thử tự động, kiểm tra tính nhất quán báo cáo và kiểm tra toàn vẹn bằng chứng đều đạt trạng thái PASS tuyệt đối.

**Kết luận:** Điểm nghẽn giao thức RQ3 chính thức **ĐÃ ĐƯỢC GIẢI TỎA HOÀN TOÀN** (`RESOLVED`). Hồ sơ báo cáo học thuật và hiện vật thực nghiệm đã sẵn sàng cho cổng nghiệm thu nộp bài cuối cùng.
