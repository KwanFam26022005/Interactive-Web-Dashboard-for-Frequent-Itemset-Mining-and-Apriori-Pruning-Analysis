# Hồ Sơ Ghi Nhận Điểm Nghẽn Giao Thức RQ3 (Phase 5C RQ3 Protocol Blocker Record)

**Dự án:** Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis  
**Repository:** `D:\Projects\fim-dashboard`  
**Nhánh:** `phase/4-experiments`  
**Bản sửa đổi nộp bị ảnh hưởng (Affected Submission Revision):** `b6c1573782706af8e4c8a82cfd08dd4b521f7f2c`  
**Bản sửa đổi báo cáo bị ảnh hưởng (Affected Report Source Revision):** `b2b4c36d8b5846fc9447d2ebb22abc489add62ee`  
**Thời gian ghi nhận:** 2026-08-21  

---

## 1. Lý Do và Tình Trạng Điểm Nghẽn (Blocker Classification)

Quá trình kiểm toán độc lập phát hiện rằng thực nghiệm đo kiểm trực quan hóa RQ3 lịch sử được thực thi tại bản sửa đổi `6276e0888e0f6ef7e8e676a451b80f7831504130` và đóng băng bằng chứng tại `2f362d4a415ecf57bf3fd60d96a38aeaa579567c` đã tồn tại các điểm lệch giao thức so với phương pháp luận Phase-4A đã được phê duyệt trước đó:
1. Kích thước khung vẽ thực nghiệm được triển khai $800 \times 600\text{ px}$ thay vì chuẩn đóng băng $800 \times 500\text{ px}$.
2. Đường lưới đồ thị bị tắt với 6 vị trí tick thay vì hiển thị đúng 5 đường lưới tuyến tính cố định trên mỗi trục toạ độ.
3. Bộ sinh dữ liệu thử tải sử dụng thuật toán LCG với seed 42 thay vì thuật toán Mulberry32 với seed `0xDEADBEEF`.
4. Dữ liệu thử tải bị phân mảnh thành 4 tệp JSON riêng biệt thay vì 1 tệp `workload_data.json` duy nhất.
5. Ngữ nghĩa cập nhật dữ liệu tại chỗ tái tạo toàn bộ toạ độ điểm thay vì bảo toàn bất biến 50% điểm gốc và dịch chuyển đúng 50% ($y_i \leftarrow (y_i + 0.1) \pmod{1.0}$).
6. Giao thức ổn định giữa các lần chạy sử dụng `double-rAF + setTimeout(16ms)` thay vì chu kỳ giải phóng đối tượng đồ họa, dọn sạch DOM và độ trễ chờ cố định 100 ms.

**Kết luận:** Trạng thái sẵn sàng nộp của Phase 5C tạm thời bị chặn đối với phần RQ3 cho đến khi thực nghiệm RQ3 chuẩn hoá được tái thực thi và thẩm định độc lập.

---

## 2. Phạm Vi Nội Dung Bị Ảnh Hưởng (Affected Scope)

- **Các mục báo cáo:**
  - Mục 7.4: Giao thức đo kiểm trực quan hóa đối chứng (RQ3)
  - Mục 8.3: Kết quả RQ3 — So sánh hiệu năng trực quan hóa Front-End
  - Mục 9.2: Thảo luận lựa chọn thư viện trực quan hóa
  - Mục 10.2: Đe dọa giá trị thực nghiệm RQ3 (nơi đề cập thông số giao thức cũ)
- **Các hiện vật đồ họa và bảng biểu:**
  - Hình 5 (`F5_visualization_initial_render.svg`)
  - Hình 6 (`F6_visualization_update.svg`)
  - Bảng 3 (`T3_rq3_visualization_performance.csv`)
  - Tệp tóm tắt và dữ liệu thô RQ3 lịch sử (`visualization_runs.csv`, `visualization_summary.csv`)
- **Hồ sơ phát hành:** Các mục RQ3 trong `REPORT_RELEASE_MANIFEST.json`.

---

## 3. Phạm Vi Hoàn Toàn Bất Biến & Không Bị Ảnh Hưởng (Unaffected Scope)

Các thành phần sau đây đã được kiểm chứng độc lập và duy trì tính toàn vẹn 100%:
- **Toàn bộ kết quả RQ1 (Ảnh hưởng của Ngưỡng Hỗ trợ):** Tệp dữ liệu thô `mushroom_support_runs.csv`, tệp tóm tắt `mushroom_support_summary.csv`, Bảng 1 (`T1`), Hình 1 (`F1`), Hình 2 (`F2`), Hình 3 (`F3`).
- **Toàn bộ kết quả RQ2 (Động thái Cắt tỉa Apriori):** Tệp dữ liệu thô `mushroom_pruning_levels.csv`, tệp tóm tắt `mushroom_pruning_summary.csv`, Bảng 2 (`T2`), Bảng phụ lục Bảng T2b (`T2b`), Hình 4 (`F4`).
- **Kiến trúc hệ thống và mã nguồn phần mềm:** Toàn bộ tầng domain, tầng HTTP, cơ sở dữ liệu MySQL InnoDB và bảng điều khiển PHP/AJAX/ECharts.
- **Danh mục tài liệu tham khảo:** Toàn bộ 15 mục tài liệu đã được thẩm định trong `REPORT_REFERENCES.md` và `REFERENCE_VERIFICATION_LEDGER.md`.

---

## 4. Kế Hoạch Khắc Phục (Remediation Roadmap)

1. **Phase 4D-R1 (Hoàn thành):** Phục hồi cấu hình giao thức chuẩn, bộ sinh dữ liệu Mulberry32, tệp `workload_data.json` đơn nhất, các adapter thư viện 800x500 px với 5 đường lưới, và lưu trữ dữ liệu lịch sử vào `experiments/diagnostic/rq3_6276_protocol_deviation/`.
2. **Phase 4D-R2 & 4D-R3 (Hoàn thành & Đã chấp thuận):** Thực thi phiên chạy hình thức thay thế (120 quan sát hoàn tất 100% không lỗi) và ghi nhận hồ sơ chấp thuận chuẩn hoá tại `experiments/evidence/RQ3_REPLACEMENT_ACCEPTANCE.json`. Dữ liệu thô và tóm tắt RQ3 thay thế đã chính thức được công nhận.
3. **Phase 4E-R1 (Hoàn thành):** Tái tạo bảng Bảng 3 (`T3`), Hình 5 (`F5`), Hình 6 (`F6`) từ dữ liệu thực nghiệm RQ3 chuẩn hoá mới, làm mới báo cáo bằng chứng `PHASE_4_EMPIRICAL_FINDINGS.md` và đóng băng `phase4_evidence_manifest.json`.
4. **Phase 5C-R1 (Giai đoạn tiếp theo):** Cập nhật số liệu và thảo luận RQ3 trong `MIDTERM_REPORT_FINAL.md`, giải phóng hoàn toàn điểm nghẽn và đóng băng `REPORT_RELEASE_MANIFEST.json` mới cho gói nộp bài.
