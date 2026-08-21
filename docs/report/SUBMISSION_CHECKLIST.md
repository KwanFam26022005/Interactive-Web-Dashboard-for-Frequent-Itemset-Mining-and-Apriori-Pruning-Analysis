# Bảng Kiểm Hoàn Thiện Hồ Sơ Nộp Báo Cáo Giữa Kỳ (Submission Checklist)

**Đề tài:** Xây dựng Bảng điều khiển Web tương tác phục vụ Khai phá Tập mục Phổ biến và Phân tích Động thái Cắt tỉa Apriori  
*(Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis)*  
**Môn học:** Ứng Dụng và Lập Trình Web (Web Programming and Applications)  
**Mã nguồn & Dữ liệu:** `D:\Projects\fim-dashboard`  
**Nhánh thực nghiệm:** `phase/4-experiments`  
**Tài liệu nộp chính thức:** `docs/report/MIDTERM_REPORT_FINAL.md`  
**Thời gian cập nhật:** 2026-08-21  

---

## 1. Tiêu Đề, Thông Tin Đề Tài & Cấu Trúc Báo Cáo

- [x] **Tiêu đề học thuật:** Tiêu đề tiếng Việt và phụ đề tiếng Anh được chuẩn hóa chính xác, phản ánh đầy đủ hai trụ cột: ứng dụng web tương tác và phân tích thực nghiệm Apriori.
- [x] **Thông tin môn học:** Ghi rõ môn học *Ứng Dụng và Lập Trình Web*, thời gian thực hiện (Tháng 8/2026).
- [x] **Tóm tắt báo cáo (Abstract):** Độ dài cô đọng (~320 từ tiếng Việt), bao quát đầy đủ: bài toán, kiến trúc hệ thống, phương pháp nghiên cứu, tập dữ liệu thực nghiệm, kết quả chính cho RQ1, RQ2, RQ3 và bối cảnh giới hạn. Không chứa mã băm Git hay nhật ký phát triển nội bộ.
- [x] **Cấu trúc phân mục:** Sắp xếp logic theo chuẩn báo cáo học thuật từ Mục 1 (Giới thiệu) đến Mục 13 (Tài liệu tham khảo), kèm Phụ lục A (Tính tái lập) và Phụ lục B (Bảng cắt tỉa chi tiết).

---

## 2. Tính Nhất Quán Học Thuật & Câu Hỏi Nghiên Cứu (Research Questions)

- [x] **RQ1 (Ảnh hưởng của Ngưỡng Hỗ trợ):** Phát biểu chuẩn xác; phân tích đầy đủ thời gian thực thi, không gian ứng viên, sản lượng tập mục và luật kết hợp; kết luận có phần *Trả lời RQ1* rõ ràng.
- [x] **RQ2 (Động thái và Hiệu quả Cắt tỉa Apriori):** Phát biểu chuẩn xác; phân tích tỷ lệ cắt tỉa tổng thể ($5.95\% - 29.28\%$), phân loại `singleton_scan` ($k=1$) và `join_prune` ($k \ge 2$), ghi nhận cắt tỉa tập con thực tế bắt đầu loại bỏ ứng viên từ bậc $k = 3$; kết luận có phần *Trả lời RQ2* rõ ràng.
- [x] **RQ3 (So sánh Hiệu năng Trực quan hóa Front-End):** Phát biểu chuẩn xác; so sánh 3 thư viện (Chart.js Canvas, D3.js SVG, Apache ECharts Canvas) qua 4 quy mô ($N \in [100, 1000, 5000, 10000]$); kết luận có phần *Trả lời RQ3* rõ ràng.

---

## 3. Tính Toàn Vẹn Đồ Họa & Bảng Biểu (Figures & Tables)

- [x] **Hình 1 (F1):** Thời gian thực thi Apriori theo ngưỡng hỗ trợ tối thiểu (`experiments/figures/F1_apriori_runtime_vs_support.svg`).
- [x] **Hình 2 (F2):** Khối lượng không gian ứng viên theo ngưỡng hỗ trợ (`experiments/figures/F2_candidate_volume_vs_support.svg`).
- [x] **Hình 3 (F3):** Khối lượng tập mục phổ biến và luật kết hợp (`experiments/figures/F3_pattern_output_vs_support.svg`).
- [x] **Hình 4 (F4):** Động thái phân tầng ứng viên và tỷ lệ cắt tỉa qua 5 ngưỡng hỗ trợ (`experiments/figures/F4_pruning_dynamics_per_level.svg`).
- [x] **Hình 5 (F5):** Độ trễ khởi tạo biểu đồ ban đầu (`experiments/figures/F5_visualization_initial_render.svg`).
- [x] **Hình 6 (F6):** Độ trễ cập nhật dữ liệu tại chỗ (`experiments/figures/F6_visualization_update.svg`).
- [x] **Bảng 1 (T1):** Tóm tắt ảnh hưởng của ngưỡng hỗ trợ đến Apriori (`experiments/tables/T1_rq1_support_effect.csv`).
- [x] **Bảng 2 (T2):** Tóm tắt tỷ lệ cắt tỉa ứng viên tổng thể (`experiments/tables/T2_rq2_overall_pruning.csv`).
- [x] **Bảng 3 (T3):** So sánh độ trễ hiển thị và cập nhật của 3 thư viện đồ họa (`experiments/tables/T3_rq3_visualization_performance.csv`).
- [x] **Bảng T2b (Phụ lục B):** Chi tiết 31 dòng dữ liệu cắt tỉa phân tầng từng bậc $k$ (`experiments/tables/T2b_rq2_per_level_pruning.csv`).
- [x] **Nguồn trích dẫn hình/bảng:** Ghi nhận chuẩn mực: *"Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả."*

---

## 4. Thẩm Định Nguồn Tham Khảo & Trích Dẫn (Citations & Bibliography)

- [x] **Danh mục tài liệu tham khảo 15 mục:** Đầy đủ 15 tài liệu đã được thẩm định qua nguồn chính thức, phân loại rõ ràng (`ACADEMIC_PRIMARY`, `ACADEMIC_SECONDARY`, `DATASET`, `SOFTWARE`, `DOCUMENTATION`, `STANDARD`).
- [x] **Định danh DOI UCI Mushroom:** Ghi nhận chuẩn xác `10.24432/C5959T` (không còn bất kỳ lỗi nào với `C59591`).
- [x] **Phân định nguồn dữ liệu UCI vs Dẫn xuất nội bộ:** Tách biệt rõ giữa siêu dữ liệu gốc của UCI (8,124 bản ghi, 22 thuộc tính + 1 nhãn lớp, DOI `[7]`) và định dạng tiếp nhận nội bộ của dự án (23 trường vật lý, 119 mục phân loại phân biệt theo `dataset_manifest.json`).
- [x] **Năm phát hành phần mềm:** 
  - Bootstrap v5.3.8 ghi nhận năm **2025** (phát hành 25/08/2025).
  - Apache ECharts v5.6.0 ghi nhận năm **2024** (phát hành 28/12/2024).
  - jQuery v3.7.1 ghi nhận năm **2023** (phát hành 28/08/2023).
  - Chart.js v4.4.8 ghi nhận năm **2025** (phát hành 02/2025).
- [x] **Tài liệu tài liệu trực tuyến (Live Documentation):** Sử dụng `n.d.` kèm thời gian truy cập (Accessed: 2026-08-21) cho tài liệu PHP Manual và MySQL 8.4 Reference Manual.
- [x] **Độ bao phủ trích dẫn inline:** 100% các chỉ số trích dẫn `[1]` đến `[15]` đều được sử dụng chính xác trong văn bản báo cáo.
- [x] **Không có nhãn chờ:** Loại bỏ hoàn toàn các ký hiệu `TODO_CITATION`, `citation needed`, `[REF?]`, `[NEEDS_REFERENCE_VERIFICATION]`.

---

## 5. Bằng Chứng Thực Nghiệm & Tính Tái Lập (Evidence & Reproducibility)

- [x] **Tính bất biến của dữ liệu thực nghiệm:** Toàn bộ 6 tệp CSV dữ liệu thô và tóm tắt khớp 100% mã băm SHA-256 gốc đã đóng băng:
  - `experiments/raw/mushroom_support_runs.csv` (`022a56cbe99344c76a8fd51cbe0329a48e4804815f6b861614fb266cfe5fc641`)
  - `experiments/raw/mushroom_pruning_levels.csv` (`613632ed7fd961ba155b8ca92ad23a2e30d271d6663ffec0d034bd6176303c11`)
  - `experiments/processed/mushroom_support_summary.csv` (`1b60921ada3edbb2f4625683338729d3e8f0dc090ae9782b3746bbcb7798f0d2`)
  - `experiments/processed/mushroom_pruning_summary.csv` (`b89a2fb983113861a7df23ed3832fc5fa983e3b3bdcbc3784851018540c804f2`)
  - `experiments/raw/visualization_runs.csv` (`10d6175b2948ed5f96b131085e12c0301ffc1f21dab12d9dd44a7234aac0d781`)
  - `experiments/processed/visualization_summary.csv` (`f7ffeb4807363276b4779da8b20dafbe931e33702d0452035f8db83ac4c65210`)
- [x] **Kiểm thử tự động:** Toàn bộ 1,200+ kiểm thử đơn vị, kiểm tra tính nhất quán báo cáo và kiểm tra cấu hình thực nghiệm đều vượt qua với 0 lỗi, 0 cảnh báo.

---

## 6. Trạng Thái Hoàn Thiện & Xuất Bản

- [x] **Văn bản Markdown đóng băng:** `docs/report/MIDTERM_REPORT_FINAL.md` đã sẵn sàng làm tài liệu nguồn chính thức.
- [ ] **Biên dịch định dạng nộp (PDF / DOCX):** Chờ thực hiện bước xuất bản định dạng cuối theo yêu cầu của hội đồng / giảng viên hướng dẫn.
