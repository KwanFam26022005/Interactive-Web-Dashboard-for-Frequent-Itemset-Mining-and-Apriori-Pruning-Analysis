# Danh Mục Tài Liệu Tham Khảo (Authoritative Bibliography)

Tài liệu này tổng hợp toàn bộ các tài liệu học thuật, bộ dữ liệu chuẩn, tài liệu phần mềm và đặc tả tiêu chuẩn công nghệ được sử dụng trong Báo cáo Giữa kỳ. Toàn bộ các mục tham khảo đã được đối chiếu và xác thực trực tiếp qua các nguồn xuất bản gốc (ACM Digital Library, IEEE Xplore, ScienceDirect, VLDB Endowment, UCI ML Repository, IETF RFC Editor, và các kho mã nguồn chính thức).

---

## 1. Tài Liệu Học Thuật Khởi Nguồn & Nền Tảng (Academic Primary & Secondary Sources)

1. **[1] Agrawal, R., Imieliński, T., & Swami, A. (1993).**  
   *Mining Association Rules between Sets of Items in Large Databases.*  
   In *Proceedings of the 1993 ACM SIGMOD International Conference on Management of Data (SIGMOD '93)*, Washington, D.C., USA, May 26–28, 1993, pp. 207–216.  
   **DOI:** [10.1145/170035.170072](https://doi.org/10.1145/170035.170072)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Định nghĩa hình thức bài toán khai phá luật kết hợp, không gian mục, độ hỗ trợ (*support*) và độ tin cậy (*confidence*).

2. **[2] Agrawal, R., & Srikant, R. (1994).**  
   *Fast Algorithms for Mining Association Rules in Large Databases.*  
   In *Proceedings of the 20th International Conference on Very Large Data Bases (VLDB '94)*, Santiago, Chile, September 12–15, 1994, pp. 487–499.  
   **Authoritative URL:** [https://www.vldb.org/conf/1994/P487.PDF](https://www.vldb.org/conf/1994/P487.PDF)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Thuật toán Apriori, nguyên lý tính chất phản đơn điệu (*Apriori anti-monotonicity principle*), thủ tục sinh ứng viên $L_{k-1} \Join L_{k-1}$ và cơ chế cắt tỉa tập con không phổ biến.

3. **[3] Han, J., Pei, J., & Yin, Y. (2000).**  
   *Mining Frequent Patterns without Candidate Generation.*  
   In *Proceedings of the 2000 ACM SIGMOD International Conference on Management of Data (SIGMOD '00)*, Dallas, TX, USA, May 15–18, 2000, pp. 1–12.  
   **DOI:** [10.1145/342009.335372](https://doi.org/10.1145/342009.335372)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Nền tảng lý thuyết thuật toán FP-Growth, cấu trúc dữ liệu cây tiền tố liên kết bộ nhớ (*FP-tree*) và khai phá mẫu tăng trưởng không sinh ứng viên.

4. **[4] Tan, P.-N., Steinbach, M., Karpatne, A., & Kumar, V. (2018).**  
   *Introduction to Data Mining (2nd Edition).*  
   Pearson, Boston, MA, USA.  
   **ISBN-13:** 978-0133128901  
   **Phân loại:** `ACADEMIC_SECONDARY`  
   **Nội dung hỗ trợ:** Thước đo độ nâng (*lift*), phân tích tương quan phụ thuộc và cấu trúc không gian mạng lưới tập con (*itemset lattice*).

5. **[5] Bostock, M., Ogievetsky, V., & Heer, J. (2011).**  
   *D³: Data-Driven Documents.*  
   *IEEE Transactions on Visualization and Computer Graphics*, 17(12), pp. 2301–2309, Dec. 2011.  
   **DOI:** [10.1109/TVCG.2011.185](https://doi.org/10.1109/TVCG.2011.185)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Mô hình biến đổi tài liệu hướng dữ liệu (DOM/SVG) và kiến trúc trực quan hóa web dựa trên phần tử đồ họa vector.

6. **[6] Li, D., Mei, H., Shen, Y., Su, S., Zhang, W., Wang, J., Zu, M., & Chen, W. (2018).**  
   *ECharts: A Declarative Framework for Rapid Construction of Web-based Visualization.*  
   *Visual Informatics*, 2(2), pp. 136–146, June 2018.  
   **DOI:** [10.1016/j.visinf.2018.04.011](https://doi.org/10.1016/j.visinf.2018.04.011)  
   **Phân loại:** `ACADEMIC_PRIMARY`  
   **Nội dung hỗ trợ:** Kiến trúc khai báo trực quan hóa dữ liệu web và nền tảng hiển thị của Apache ECharts.

---

## 2. Bộ Dữ Liệu Chuẩn Thực Nghiệm (Benchmark Dataset)

7. **[7] UCI Machine Learning Repository: Mushroom Data Set.**  
   *Agaricus and Lepiota Mushroom Dataset (agaricus-lepiota.data).*  
   **Donor:** Jeff Schlimmer (1987). Derived from *The Audubon Society Field Guide to North American Mushrooms* (1981).  
   **Dataset ID:** 73  
   **DOI:** [10.24432/C59591](https://doi.org/10.24432/C59591)  
   **License:** Creative Commons Attribution 4.0 International (CC BY 4.0)  
   **URL:** [https://archive.ics.uci.edu/dataset/73/mushroom](https://archive.ics.uci.edu/dataset/73/mushroom)  
   **Phân loại:** `DATASET`  
   **Nội dung hỗ trợ:** Đặc tả bộ dữ liệu thực nghiệm $N = 8,124$ giao dịch, 23 trường phân loại vật lý (gồm lớp $c_1$ và 22 thuộc tính $c_2..c_{23}$), 119 mục phân loại chuẩn hóa.

---

## 3. Thư Viện Phần Mềm Trực Quan Hóa & Front-End (Software Packages)

8. **[8] Bostock, M., & D3 Contributors (2024).**  
   *D3.js: JavaScript library for visualizing data (Version 7.9.0).*  
   **License:** ISC License  
   **Package:** `npm:d3@7.9.0` | **Repository:** [https://github.com/d3/d3](https://github.com/d3/d3)  
   **URL:** [https://d3js.org/](https://d3js.org/)  
   **Phân loại:** `SOFTWARE`  
   **Nội dung hỗ trợ:** Thư viện đối chứng SVG trong thực nghiệm RQ3.

9. **[9] Chart.js Open Source Project (2025).**  
   *Chart.js: Simple yet flexible JavaScript charting for designers & developers (Version 4.4.8).*  
   **License:** MIT License  
   **Package:** `npm:chart.js@4.4.8` | **Repository:** [https://github.com/chartjs/Chart.js](https://github.com/chartjs/Chart.js)  
   **URL:** [https://www.chartjs.org/](https://www.chartjs.org/)  
   **Phân loại:** `SOFTWARE`  
   **Nội dung hỗ trợ:** Thư viện đối chứng Canvas trong thực nghiệm RQ3.

10. **[10] The Apache Software Foundation (2025).**  
    *Apache ECharts: An Open Source JavaScript Visualization Library (Version 5.6.0).*  
    **License:** Apache License 2.0  
    **Package:** `npm:echarts@5.6.0` | **Repository:** [https://github.com/apache/echarts](https://github.com/apache/echarts)  
    **URL:** [https://echarts.apache.org/](https://echarts.apache.org/)  
    **Phân loại:** `SOFTWARE`  
    **Nội dung hỗ trợ:** Thư viện trực quan hóa chính của bảng điều khiển web và đối chứng Canvas trong RQ3.

11. **[11] Bootstrap Authors (2024).**  
    *Bootstrap: Powerful, extensible, and feature-packed frontend toolkit (Version 5.3.8).*  
    **License:** MIT License  
    **Package:** `npm:bootstrap@5.3.8` | **Repository:** [https://github.com/twbs/bootstrap](https://github.com/twbs/bootstrap)  
    **URL:** [https://getbootstrap.com/](https://getbootstrap.com/)  
    **Phân loại:** `SOFTWARE`  
    **Nội dung hỗ trợ:** Khung giao diện web đáp ứng của bảng điều khiển.

12. **[12] OpenJS Foundation (2023).**  
    *jQuery: Fast, small, and feature-rich JavaScript library (Version 3.7.1).*  
    **License:** MIT License  
    **Repository:** [https://github.com/jquery/jquery](https://github.com/jquery/jquery)  
    **URL:** [https://jquery.com/](https://jquery.com/)  
    **Phân loại:** `SOFTWARE`  
    **Nội dung hỗ trợ:** Xử lý sự kiện DOM và truyền thông AJAX bất đồng bộ phía client.

---

## 4. Ngôn Ngữ, Cơ Sở Dữ Liệu & Tiêu Chuẩn Công Nghệ (Standards & Documentation)

13. **[13] The PHP Group (2024).**  
    *PHP Manual: Language Reference & Runtime Architecture.*  
    **Environment Runtime:** PHP 8.3.30 (Formal Benchmark Environment) / Language Target: PHP 8.2+  
    **URL:** [https://www.php.net/docs.php](https://www.php.net/docs.php)  
    **Phân loại:** `DOCUMENTATION`  
    **Nội dung hỗ trợ:** Ngôn ngữ lập trình phụ trợ, cú pháp đối tượng và môi trường thực thi tính toán.

14. **[14] Oracle Corporation (2024).**  
    *MySQL 8.4 Reference Manual: InnoDB Storage Engine & Performance.*  
    **Environment Server:** MySQL 8.4.3 (Formal Benchmark Environment)  
    **URL:** [https://dev.mysql.com/doc/refman/8.4/en/](https://dev.mysql.com/doc/refman/8.4/en/)  
    **Phân loại:** `DOCUMENTATION`  
    **Nội dung hỗ trợ:** Hệ quản trị cơ sở dữ liệu quan hệ, cấu trúc bảng InnoDB và chỉ mục khóa ngoại.

15. **[15] Bray, T. (Ed.) (2017).**  
    *RFC 8259: The JavaScript Object Notation (JSON) Data Interchange Format.*  
    **Standards Track:** Internet Engineering Task Force (IETF), RFC 8259, STD 90.  
    **DOI:** [10.17487/RFC8259](https://doi.org/10.17487/RFC8259)  
    **URL:** [https://www.rfc-editor.org/info/rfc8259](https://www.rfc-editor.org/info/rfc8259)  
    **Phân loại:** `STANDARD`  
    **Nội dung hỗ trợ:** Chuẩn định dạng trao đổi dữ liệu JSON giữa PHP backend và AJAX client.
