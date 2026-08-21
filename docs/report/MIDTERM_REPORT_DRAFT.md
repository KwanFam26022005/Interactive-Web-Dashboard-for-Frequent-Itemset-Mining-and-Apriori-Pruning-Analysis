# BÁO CÁO GIỮA KỲ ĐỀ TÀI NGHIÊN CỨU VÀ PHÁT TRIỂN ỨNG DỤNG WEB

---

**ĐỀ TÀI:**  
### XÂY DỰNG BẢNG ĐIỀU KHIỂN WEB TƯƠNG TÁC PHỤC VỤ KHAI PHÁ TẬP MỤC PHỔ BIẾN VÀ PHÂN TÍCH ĐỘNG THÁI CẮT TỈA APRIORI  
*(Interactive Web Dashboard for Frequent Itemset Mining and Apriori Pruning Analysis)*

**Môn học:** Ứng Dụng và Lập Trình Web (Web Programming and Applications)  
**Mã nguồn & Dữ liệu thực nghiệm:** `D:\Projects\fim-dashboard`  
**Nhánh thực nghiệm đóng băng:** `phase/4-experiments`  
**Thời gian thực hiện:** Tháng 8 Năm 2026  

---

## TÓM TẮT BÁO CÁO (ABSTRACT)

Báo cáo này trình bày quá trình thiết kế, hiện thực hóa và đánh giá thực nghiệm một hệ thống ứng dụng web hoàn chỉnh phục vụ bài toán Khai phá Tập mục Phổ biến (*Frequent Itemset Mining - FIM*) [1] và Khai phá Luật kết hợp (*Association Rule Mining*) [1], [2]. Dự án giải quyết khoảng cách cố hữu giữa các thuật toán khai phá dữ liệu dòng lệnh mang tính hàn lâm và nhu cầu giám sát, phân tích trực quan hóa tương tác của người dùng trên nền tảng web hiện đại.

Hệ thống được phát triển theo kiến trúc phân lớp nhẹ (*Lightweight Layered Architecture*) với các endpoint PHP mỏng kết hợp các lớp miền (*domain*), HTTP và lưu trữ (*persistence*) được phân tách trách nhiệm rõ ràng, sử dụng ngăn xếp công nghệ **PHP 8.2+** [13], cơ sở dữ liệu quan hệ **MySQL 8.4** [14] và giao diện người dùng đáp ứng dựa trên **HTML5/Bootstrap 5** [11] / **jQuery** [12] / **AJAX** kết hợp thư viện trực quan hóa **Apache ECharts** [6], [10]. Điểm đóng góp cốt lõi của đề tài là sự kết hợp chặt chẽ giữa việc xây dựng một sản phẩm web hoàn chỉnh với một khung nghiên cứu thực nghiệm có kiểm soát chặt chẽ (*controlled empirical study*) nhằm trả lời ba câu hỏi nghiên cứu (RQ1, RQ2, RQ3).

Dựa trên bộ dữ liệu thực nghiệm chuẩn UCI Mushroom (*agaricus-lepiota.data*, $N = 8,124$ giao dịch, 119 mục phân loại chuẩn) [7], các thực nghiệm được đóng băng với 10 lần lặp độc lập sau 2 lần chạy làm nóng, sử dụng phương pháp thống kê phi tham số (Trung vị và Khoảng liên phân vị - IQR). Không có quan sát thô nào bị xóa bỏ khỏi bộ dữ liệu; phương pháp trung vị được sử dụng nhằm giảm độ nhạy của thống kê tổng hợp đối với các quan sát cực trị. Kết quả thực nghiệm chỉ ra rằng:
1. Việc hạ ngưỡng hỗ trợ tối thiểu ($\text{min\_support}$) từ $0.60$ xuống $0.35$ dẫn đến sự mở rộng đáng kể (*substantial expansion*) không gian tìm kiếm ứng viên (từ $185$ lên $2,131$), khối lượng tập mục phổ biến ($51$ lên $1,189$), số lượng luật kết hợp ($223$ lên $11,055$) và thời gian thực thi trung vị của thuật toán Apriori (từ $523.072\text{ ms}$ lên $14,047.443\text{ ms}$) (RQ1).
2. Tỷ lệ cắt tỉa ứng viên theo tính chất Apriori (*Apriori-property subset pruning*) [2] gia tăng từ $5.95\%$ lên $29.28\%$ tổng số ứng viên sinh ra khi giảm ngưỡng hỗ trợ, trong đó việc cắt tỉa tập con thực tế bắt đầu loại bỏ ứng viên từ bậc độ dài $k = 3$, giúp loại bỏ hàng trăm ứng viên không tiềm năng trước bước đánh giá độ hỗ trợ trên tập dữ liệu giao dịch trong bộ nhớ (RQ2).
3. Trong thực nghiệm đối chứng hiệu năng hiển thị front-end cô lập dưới thước đo chuẩn hóa độ trễ quan sát hai khung hình (*render-to-two-frame-observation latency*), Chart.js (sử dụng Canvas) [9] ghi nhận độ trễ thấp nhất ở khối lượng dữ liệu dày đặc ($N = 10,000$ điểm), tiếp theo là D3.js (sử dụng SVG) [5], [8] và Apache ECharts (sử dụng Canvas) [6], [10], đồng thời ở các quy mô dữ liệu lớn ($N \ge 5,000$), việc cập nhật dữ liệu tại chỗ (*in-place update*) có độ trễ thấp hơn so với việc khởi dựng biểu đồ ban đầu trên cả ba thư viện (RQ3).

---

## 1. GIỚI THIỆU (INTRODUCTION)

Trong kỷ nguyên bùng nổ dữ liệu số, việc phát hiện các mẫu tri thức ẩn sâu và các mối tương quan có giá trị trong các cơ sở dữ liệu giao dịch lớn đóng vai trò then chốt trong nhiều lĩnh vực ứng dụng, từ phân tích giỏ hàng thương mại (*market basket analysis*), hệ thống gợi ý sản phẩm (*recommender systems*), đến phát hiện bất thường và phân tích dữ liệu sinh học. Khai phá tập mục phổ biến (*Frequent Itemset Mining - FIM*) và khai phá luật kết hợp (*Association Rule Mining*), khởi xướng bởi Agrawal, Imieliński, Swami (1993) [1] và Agrawal, Srikant (1994) [2], là một trong những trụ cột lý thuyết quan trọng nhất của lĩnh vực Khai phá dữ liệu (*Data Mining*).

Mặc dù nền tảng lý thuyết của thuật toán Apriori [2] đã được nghiên cứu sâu rộng, việc ứng dụng thuật toán này trong môi trường web thực tế vẫn đối mặt với những thách thức đáng kể:
- **Tính toán nặng phía máy chủ:** Bản chất duyệt không gian tìm kiếm tổ hợp dạng mạng lưới (*lattice*) [4] đòi hỏi nhiều lượt kiểm tra và so khớp tập con, dễ dẫn đến quá tải tài nguyên CPU và bộ nhớ khi ngưỡng hỗ trợ tối thiểu bị hạ thấp.
- **Rào cản giao tiếp người dùng:** Đa số các công cụ khai phá truyền thống hoạt động dưới dạng dòng lệnh (*Command-Line Interface - CLI*) hoặc các thư viện học máy độc lập, tạo ra khoảng cách lớn đối với người phân tích kinh doanh cần tương tác trực tiếp với tham số và quan sát biểu đồ phân tích.
- **Áp lực hiển thị phía máy khách (Client-Side Rendering):** Các tập mẫu phổ biến và luật kết hợp khi được trích xuất thường tạo ra hàng ngàn đến hàng vạn điểm dữ liệu. Việc trực quan hóa các mối quan hệ đa chiều (Support vs. Confidence vs. Lift) trên trình duyệt web đòi hỏi các giải pháp thư viện đồ họa tối ưu nhằm duy trì tốc độ khung hình mượt mà và khả năng tương tác liên tục.

Nhằm giải quyết đồng thời các thách thức trên trong khuôn khổ môn học **Ứng Dụng và Lập Trình Web**, đề tài này xây dựng một hệ sinh thái ứng dụng web hoàn chỉnh:
- Tầng phụ trợ (Back-end) phát triển bằng PHP thuần hướng đối tượng [13] với kiến trúc phân lớp nhẹ, triển khai thuật toán Apriori [2] với cơ chế đo lường chi tiết động thái cắt tỉa qua từng bậc độ dài $k$.
- Tầng lưu trữ (Database) sử dụng MySQL [14] với lược đồ quan hệ chuẩn hóa cao độ (`datasets`, `transactions`, `transaction_items`, `experiment_runs`, `experiment_run_levels`), tối ưu hóa chỉ mục cho các truy vấn phân tích.
- Tầng giao diện (Front-end) là bảng điều khiển web tương tác hiện đại ứng dụng AJAX, Bootstrap 5 [11], jQuery [12] và Apache ECharts [6], [10].
- Khung nghiên cứu thực nghiệm (Empirical Benchmark Pipeline) tự động hóa, đóng băng dữ liệu và kiểm chứng tính toán học chặt chẽ.

---

## 2. PHÁT BIỂU BÀI TOÁN (PROBLEM STATEMENT)

Bài toán trung tâm của đề tài được định nghĩa trên hai phương diện:

1. **Phương diện Kỹ thuật Web & Hệ thống:**  
   Làm thế nào để thiết kế một kiến trúc web có khả năng tiếp nhận các tệp dữ liệu giao dịch tùy biến, tiền xử lý và lưu trữ bền vững, đồng thời cung cấp giao diện lập trình ứng dụng (HTTP JSON API) phi trạng thái để tiếp nhận yêu cầu khai phá Apriori từ giao diện người dùng? Trình duyệt gửi yêu cầu AJAX bất đồng bộ để tránh tải lại toàn trang, trong khi máy chủ xử lý đồng bộ và trả kết quả JSON với dữ liệu rút gọn để bảng điều khiển cập nhật giao diện tương tác.

2. **Phương diện Động thái Thuật toán & Hiệu năng Trực quan hóa:**  
   Cần khảo sát một cách định lượng và minh bạch mối quan hệ giữa ngưỡng hỗ trợ tối thiểu với không gian ứng viên sinh ra, khối lượng mẫu phát hiện và thời gian thực thi của thuật toán Apriori [2] trên một tập dữ liệu thực tế. Đồng thời, cần đo lường hiệu quả cắt tỉa thực tế của tính chất Apriori qua từng bậc $k$, và đánh giá hiệu năng hiển thị của các công nghệ thư viện đồ họa web (DOM/SVG vs. HTML5 Canvas) [5], [6] khi phải gánh tải các tập dữ liệu điểm dày đặc.

---

## 3. MỤC TIÊU VÀ CÂU HỎI NGHIÊN CỨU (OBJECTIVES AND RESEARCH QUESTIONS)

### 3.1 Mục Tiêu Dự Án
- **Mục tiêu 1:** Xây dựng ứng dụng web quản lý tập dữ liệu, cấu hình khai phá và hiển thị bảng điều khiển tương tác bằng PHP [13], MySQL [14], Bootstrap [11] và Apache ECharts [10].
- **Mục tiêu 2:** Hiện thực hóa động cơ khai phá Apriori và sinh luật kết hợp độc lập, mô-đun hóa cao, tích hợp bộ thu thập số liệu cắt tỉa (*pruning instrumentation*) chi tiết theo từng cấp độ [2].
- **Mục tiêu 3:** Thiết lập khung thực nghiệm chuẩn hóa, bảo đảm tính tái lập (*reproducibility*) và truy xuất nguồn gốc mật mã (*cryptographic provenance*) thông qua mã băm SHA-256.

### 3.2 Các Câu Hỏi Nghiên Cứu (Research Questions)
Để định hướng đánh giá thực nghiệm, ba câu hỏi nghiên cứu hình thức được thiết lập:

- **RQ1 (Ảnh hưởng của Ngưỡng Hỗ trợ):**  
  *Ngưỡng hỗ trợ tối thiểu ($\text{min\_support}$) ảnh hưởng như thế nào đến khối lượng ứng viên sinh ra, số lượng tập mẫu phổ biến / luật kết hợp được trích xuất và thời gian thực thi của thuật toán Apriori?*
- **RQ2 (Động thái và Hiệu quả Cắt tỉa Apriori):**  
  *Cơ chế cắt tỉa tập con theo tính chất Apriori ($\text{Apriori-property subset pruning}$) đạt hiệu quả ra sao trong việc thu hẹp không gian tìm kiếm ứng viên qua từng bậc độ dài tập mục $k$?*
- **RQ3 (So sánh Hiệu năng Trực quan hóa Front-End):**  
  *Ba thư viện trực quan hóa web D3.js (triển khai SVG) [8], Chart.js (triển khai Canvas) [9] và Apache ECharts (triển khai Canvas) [10] so sánh như thế nào về độ trễ khởi tạo biểu đồ ban đầu ($\text{initial render}$) và độ trễ cập nhật dữ liệu tại chỗ ($\text{in-place data update}$) dưới các khối lượng dữ liệu điểm phân tán tương đương?*

---

## 4. CƠ SỞ LÝ THUYẾT (THEORETICAL BACKGROUND)

### 4.1 Khai Phá Tập Mục Phổ Biến (Frequent Itemset Mining)
Cho $\mathcal{I} = \{i_1, i_2, \dots, i_m\}$ là tập hợp tất cả các mục (*items*) phân biệt [1]. Một tập hợp $X \subseteq \mathcal{I}$ được gọi là một tập mục (*itemset*). Một tập mục chứa $k$ mục được gọi là một $k$-tập mục (*$k$-itemset*).

Cho cơ sở dữ liệu giao dịch $\mathcal{D} = \{T_1, T_2, \dots, T_N\}$, trong đó mỗi giao dịch $T_j \subseteq \mathcal{I}$ gắn liền với một định danh duy nhất ($TID$). Một giao dịch $T$ được gọi là chứa tập mục $X$ nếu và chỉ nếu $X \subseteq T$.

### 4.2 Độ Hỗ Trợ (Support)
Độ hỗ trợ tuyệt đối (*absolute support count*) của tập mục $X$ trong $\mathcal{D}$, ký hiệu là $\sigma(X)$, là số lượng giao dịch chứa $X$ [1]:
$$\sigma(X) = |\{T \in \mathcal{D} \mid X \subseteq T\}|$$

Độ hỗ trợ tương đối (*relative support*) của tập mục $X$, ký hiệu là $\text{supp}(X)$, là tỷ lệ phần trăm các giao dịch trong $\mathcal{D}$ chứa $X$ [1]:
$$\text{supp}(X) = \frac{\sigma(X)}{N} = \frac{|\{T \in \mathcal{D} \mid X \subseteq T\}|}{|\mathcal{D}|}$$

Một tập mục $X$ được coi là **phổ biến** (*frequent itemset*) nếu $\text{supp}(X) \ge \text{minsup}$, trong đó $\text{minsup} \in (0, 1]$ là ngưỡng hỗ trợ tối thiểu do người dùng định nghĩa [1]. Tập hợp tất cả các $k$-tập mục phổ biến được ký hiệu là $L_k$.

### 4.3 Khai Phá Luật Kết Hợp và Độ Tin Cậy (Confidence)
Một luật kết hợp là một biểu thức có dạng [1]:
$$X \Rightarrow Y$$
trong đó $X \subset \mathcal{I}$, $Y \subset \mathcal{I}$, $X \neq \emptyset$, $Y \neq \emptyset$ và $X \cap Y = \emptyset$. Tập mục $X$ được gọi là tiền đề (*antecedent*), còn $Y$ được gọi là hệ quả (*consequent*).

Độ hỗ trợ của luật $X \Rightarrow Y$ chính là độ hỗ trợ của tập hợp $X \cup Y$ [1]:
$$\text{supp}(X \Rightarrow Y) = \text{supp}(X \cup Y)$$

Độ tin cậy (*confidence*) của luật $X \Rightarrow Y$ đo lường xác suất có điều kiện giao dịch chứa $Y$ khi đã biết giao dịch đó chứa $X$ [1]:
$$\text{conf}(X \Rightarrow Y) = P(Y \mid X) = \frac{\text{supp}(X \cup Y)}{\text{supp}(X)} = \frac{\sigma(X \cup Y)}{\sigma(X)}$$

Một luật kết hợp được chấp nhận là luật mạnh (*strong association rule*) nếu $\text{supp}(X \Rightarrow Y) \ge \text{minsup}$ và $\text{conf}(X \Rightarrow Y) \ge \text{minconf}$ [1].

### 4.4 Độ Nâng (Lift)
Độ nâng (*lift*) đo lường mức độ phụ thuộc thống kê giữa $X$ và $Y$, xác định xem sự xuất hiện của $X$ có làm tăng hay giảm khả năng xuất hiện của $Y$ so với trường hợp độc lập [4]:
$$\text{lift}(X \Rightarrow Y) = \frac{P(X \cup Y)}{P(X) \cdot P(Y)} = \frac{\text{conf}(X \Rightarrow Y)}{\text{supp}(Y)} = \frac{\text{supp}(X \cup Y)}{\text{supp}(X) \cdot \text{supp}(Y)}$$

- $\text{lift} = 1$: $X$ và $Y$ hoàn toàn độc lập thống kê [4].
- $\text{lift} > 1$: $X$ và $Y$ có tương quan đồng thuận dương (*positive correlation*) [4].
- $\text{lift} < 1$: $X$ và $Y$ có tương quan nghịch (*negative correlation*) [4].

### 4.5 Tính Chất Apriori (The Apriori Principle)
Không gian tìm kiếm tất cả các tập mục tiềm năng từ tập $\mathcal{I}$ có kích thước $2^{|\mathcal{I}|}$, tạo thành một mạng lưới tập con (*itemset lattice*) [4].

**Định lý Tính chất Phản Đơn Điệu (Anti-monotonicity Property) [2]:**  
*Mọi tập con không rỗng của một tập mục phổ biến đều phải là tập mục phổ biến.*  
$$\forall X, Y \subseteq \mathcal{I}: X \subseteq Y \implies \text{supp}(Y) \le \text{supp}(X)$$

**Hệ quả dùng cho cắt tỉa [2]:**  
*Nếu một tập mục $X$ không phổ biến ($\text{supp}(X) < \text{minsup}$), thì mọi tập siêu $Y \supset X$ đều chắc chắn không phổ biến và có thể bị loại bỏ ngay lập tức mà không cần đánh giá độ hỗ trợ.*

### 4.6 Cơ Chế Sinh Ứng Viên và Cắt Tỉa (Candidate Generation & Pruning)
Thuật toán Apriori duyệt không gian tìm kiếm theo từng cấp bậc độ dài $k$ (*level-wise breadth-first search*) [2]:

1. **Bước Khởi tạo ($k=1$ - `singleton_scan`):** Quét toàn bộ dữ liệu giao dịch để đếm độ hỗ trợ của từng mục đơn $i \in \mathcal{I}$, trích xuất $L_1$.
2. **Bước Kết nối ($k \ge 2$ - `join_step`):** Sinh tập ứng viên $C_k$ bằng cách kết nối hai tập mục $l_1, l_2 \in L_{k-1}$ có cùng tiền tố $k-2$ mục đầu tiên [2]:
   $$l_1 = \{i_1, i_2, \dots, i_{k-2}, i_{k-1}\}, \quad l_2 = \{i_1, i_2, \dots, i_{k-2}, i'_{k-1}\} \quad (i_{k-1} < i'_{k-1})$$
   $$c = l_1 \cup l_2 = \{i_1, i_2, \dots, i_{k-2}, i_{k-1}, i'_{k-1}\}$$
3. **Bước Cắt tỉa ($k \ge 2$ - `prune_step`):** Đối với mỗi ứng viên $c \in C_k$, kiểm tra xem tất cả các tập con độ dài $k-1$ của $c$ có nằm trong $L_{k-1}$ hay không [2]. Nếu tồn tại bất kỳ tập con nào không thuộc $L_{k-1}$, loại bỏ $c$ khỏi $C_k$.
4. **Bước Đếm và Lọc:** Đếm độ hỗ trợ thực tế của các ứng viên còn lại sau cắt tỉa trên tập dữ liệu và giữ lại các ứng viên đạt $\text{minsup}$ để tạo thành $L_k$.

### 4.7 So Sánh Lý Thuyết Với Thuật Toán FP-Growth (Theoretical Context)
Trong tài liệu khai phá dữ liệu, thuật toán FP-Growth (*Frequent Pattern Growth*) của Han, Pei, Yin (2000) [3] được giới thiệu phổ biến như một giải pháp thay thế tránh việc sinh ứng viên tường minh:
- **Nguyên lý:** FP-Growth nén dữ liệu giao dịch vào một cấu trúc cây tiền tố liên kết bộ nhớ (*FP-tree*), sau đó sử dụng chiến lược chia để trị (*divide-and-conquer*) để khai phá các mẫu phổ biến từ các cây điều kiện (*conditional pattern bases*) [3].
- **Đánh đổi lý thuyết:** Trong khi FP-Growth tránh việc sinh ứng viên tổ hợp [3], Apriori [2] lại thể hiện rõ ràng và minh bạch cấu trúc phân tầng $k$-itemset, cho phép quan sát trực tiếp động thái cắt tỉa tổ hợp qua từng bước. Trong khuôn khổ đề tài này, Apriori được lựa chọn hiện thực hóa chính thức nhằm phục vụ mục tiêu phân tích chẩn đoán cơ chế cắt tỉa; FP-Growth được trình bày như nền tảng đối sánh lý thuyết; hiệu năng so sánh phụ thuộc vào tập dữ liệu và cách hiện thực hóa cụ thể.

### 4.8 Các Nền Tảng Thư Viện Trực Quan Hóa Web
Việc trực quan hóa dữ liệu khai phá trên giao diện web phụ thuộc vào công nghệ dựng hình của thư viện:
- **D3.js (Data-Driven Documents):** D3.js sử dụng mô hình biến đổi phần tử tài liệu dựa trên dữ liệu [5]. Trong thực nghiệm RQ3, D3.js 7.9.0 [8] được triển khai sử dụng công nghệ SVG DOM, nơi mỗi điểm dữ liệu được ánh xạ thành một phần tử `<circle>`.
- **Chart.js & Apache ECharts:** ECharts [6] và Chart.js [9] cung cấp các mô hình cấu hình biểu đồ khai báo cấp cao. Trong thực nghiệm RQ3, Chart.js 4.4.8 [9] và Apache ECharts 5.6.0 [10] đều được cấu hình sử dụng công nghệ HTML5 Canvas rasterization.

---

## 5. YÊU CẦU HỆ THỐNG VÀ NGĂN XẾP CÔNG NGHỆ (SYSTEM REQUIREMENTS AND TECH STACK)

### 5.1 Yêu Cầu Chức Năng (Functional Requirements)
- **FR1 (Quản lý Tập Dữ Liệu):** Cho phép người dùng tải lên, kiểm tra tính hợp lệ và lưu trữ các tệp dữ liệu giao dịch ở các định dạng giỏ hàng (Basket CSV/TXT) và định dạng phân loại (Mushroom format).
- **FR2 (Cấu hình và Kích hoạt Khai Phá):** Gửi yêu cầu AJAX để thực thi Apriori trên máy chủ; backend xử lý đồng bộ trong request và trả kết quả JSON để dashboard cập nhật giao diện tương tác.
- **FR3 (Bảng Điều Khiển Trực Quan Hóa):** Hiển thị tổng quan các chỉ số thống kê, bảng dữ liệu phân trang, biểu đồ ma trận nhiệt tương quan, biểu đồ thanh phân tầng và biểu đồ phân tán tương tác.
- **FR4 (Phân Tích Động Thái Cắt Tỉa):** Trực quan hóa chi tiết tỷ lệ ứng viên sinh ra, cắt tỉa và giữ lại qua từng bậc $k$.

### 5.2 Ngăn Xếp Công Nghệ Chuẩn Hóa
Hệ thống tuân thủ cấu trúc phân lớp nhẹ:

```text
┌─────────────────────────────────────────────────────────────┐
│                    GIAO DIỆN FRONT-END                      │
│      HTML5 / CSS3 / Bootstrap 5 [11] / JavaScript (ES6+)    │
│           jQuery [12] / AJAX / Apache ECharts 5.6.0 [10]    │
└──────────────────────────────┬──────────────────────────────┘
                               │ JSON (RFC 8259) [15] / AJAX
┌──────────────────────────────▼──────────────────────────────┐
│                    TẦNG XỬ LÝ BACK-END                      │
│      PHP 8.2+ [13] (Kiến trúc phân lớp nhẹ, autoloader)     │
│       - App\Dataset\DatasetImportService                    │
│       - App\Mining\AprioriEngine (Join / Prune / Filter)    │
│       - App\Mining\AssociationRuleGenerator                 │
│       - App\Mining\HeatmapBuilder                           │
│       - App\Http\DatasetController & MiningController       │
└──────────────────────────────┬──────────────────────────────┘
                               │ PDO Prepared Statements
┌──────────────────────────────▼──────────────────────────────┐
│                    TẦNG DỮ LIỆU PERSISTENCE                 │
│                 MySQL 8.4 [14] (InnoDB Storage)             │
│    - datasets, transactions, transaction_items              │
│    - experiment_runs, experiment_run_levels                 │
└─────────────────────────────────────────────────────────────┘
```

---

## 6. KIẾN TRÚC VÀ HIỆN THỰC HỆ THỐNG (SYSTEM ARCHITECTURE AND IMPLEMENTATION)

### 6.1 Kiến Trúc Tổng Thể và Phân Tách Lớp
Hệ thống sử dụng kiến trúc phân lớp nhẹ với endpoint PHP mỏng và các lớp miền, HTTP và persistence được tách trách nhiệm rõ ràng theo quyết định kiến trúc ADR-001:
- `App\Dataset\DatasetImportService`: Quản lý tiếp nhận, kiểm tra tính toàn vẹn và chuẩn hóa dữ liệu giao dịch.
- `App\Mining\AprioriEngine`: Động cơ lõi điều phối thuật toán Apriori trong bộ nhớ [2].
- `App\Mining\CandidateJoiner`: Dịch vụ kết nối tiền tố $L_{k-1} \Join L_{k-1}$ [2].
- `App\Mining\CandidatePruner`: Dịch vụ kiểm tra và cắt tỉa tập con không phổ biến [2].
- `App\Mining\SupportCounter`: Dịch vụ đếm tần suất xuất hiện của ứng viên trên mảng giao dịch trong bộ nhớ.
- `App\Mining\FrequentFilter`: Dịch vụ lọc tập mục thỏa mãn ngưỡng hỗ trợ.
- `App\Mining\AssociationRuleGenerator`: Dịch vụ sinh luật kết hợp và tính toán support, confidence, lift [1], [4].
- `App\Mining\HeatmapBuilder`: Dịch vụ tạo ma trận nhiệt đồng xuất hiện của Top-N mục phổ biến nhất.
- `App\Persistence\DatasetRepository`: Truy xuất tập dữ liệu và nạp mảng giao dịch.
- `App\Persistence\ExperimentRunRepository`: Ghi nhận tóm tắt kết quả chạy thực nghiệm rút gọn.
- `App\Http\MiningController` & `App\Http\DatasetController`: Điều phối yêu cầu HTTP và xác thực dữ liệu đầu vào.
- `App\Http\MiningResponseAssembler`: Lắp ráp phản hồi JSON chứa dữ liệu trực quan hóa Top-N và số liệu tóm tắt.

### 6.2 Mô Hình Xử Lý Đồng Bộ Phía Máy Chủ (Synchronous Execution Flow)
Khác với các hệ thống hàng đợi tác vụ nền phức tạp, luồng khai phá trong hệ thống được thực thi **đồng bộ** trong một vòng đời yêu cầu HTTP đơn lẻ:
1. Trình duyệt gửi yêu cầu `POST /api/mining.php` chứa JSON cấu hình qua AJAX.
2. `MiningController` xác thực tham số và kiểm tra giới hạn bảo vệ (*guardrails*: tối đa 250,000 ứng viên, 50,000 luật, thời gian chạy 30 giây).
3. `DatasetRepository` nạp toàn bộ danh sách giao dịch của tập dữ liệu vào bộ nhớ RAM (`array<list<string>>`).
4. `AprioriEngine` thực thi thuật toán Apriori [2] trực tiếp trên mảng giao dịch trong bộ nhớ và ghi nhận thời gian chạy `runtime_ms`.
5. `AssociationRuleGenerator` và `HeatmapBuilder` tạo các tập luật và ma trận nhiệt tương ứng.
6. `ExperimentRunRepository` lưu trữ bản ghi tóm tắt rút gọn vào bảng `experiment_runs` và `experiment_run_levels`.
7. `MiningResponseAssembler` lắp ráp dữ liệu trực quan hóa Top-N và phát phản hồi JSON (RFC 8259) [15] về client để cập nhật giao diện.

### 6.3 Lược Đồ Cơ Sở Dữ Liệu và Chính Sách Lưu Trữ (ADR-002)
Lược đồ cơ sở dữ liệu MySQL 8.4 [14] InnoDB bao gồm 5 bảng chuẩn:
- `datasets`: Lưu trữ thông tin metadata của tập dữ liệu, mã băm SHA-256 tệp nguồn, tổng số giao dịch (`transaction_count`) và số mục phân biệt (`unique_item_count`).
- `transactions`: Lưu danh sách giao dịch chuẩn hóa gắn với `dataset_id`.
- `transaction_items`: Lưu các mục thuộc từng giao dịch theo khóa kép `(transaction_id, item_key)`.
- `experiment_runs`: Lưu trữ tóm tắt kết quả của lần chạy khai phá thành công (tham số `min_support`, `min_confidence`, thời gian `runtime_ms`, tổng số ứng viên sinh ra, cắt tỉa, đánh giá, số lượng tập mục phổ biến và số luật).
- `experiment_run_levels`: Lưu trữ số liệu chẩn đoán cắt tỉa chi tiết từng bậc độ dài $k$ (`generated`, `pruned`, `evaluated`, `frequent`, `source`).

**Chính Sách Lưu Trữ Dữ Liệu Rút Gọn và Phản Hồi Top-N (Transient vs. Persistent Policy):**  
Hệ thống lưu trữ bền vững các tập dữ liệu chuẩn hóa và bản ghi tóm tắt thực nghiệm rút gọn (`experiment_runs`, `experiment_run_levels`). Trong vòng đời request, các kết quả khai phá đầy đủ tồn tại tạm thời trong bộ nhớ để phục vụ tính toán số liệu tổng hợp. Phản hồi HTTP chỉ tuần tự hóa Top-N tập mục phổ biến và Top-N luật kết hợp theo tham số `top_n`, cùng các số liệu tổng hợp, dữ liệu ma trận nhiệt giới hạn và thông tin `result_limits` cho biết dữ liệu có bị cắt bớt hay không. Các tập kết quả chi tiết đầy đủ không được lưu bền vững và MVP không cung cấp API lịch sử để truy xuất lại chúng (`GET run-history`).

### 6.4 Giao Diện Lập Trình Ứng Dụng (HTTP JSON API Surface)
Theo quyết định kiến trúc ADR-004, hệ thống công khai các endpoint HTTP hẹp:
- `GET /api/datasets.php`: Lấy danh sách các tập dữ liệu đã nạp.
- `GET /api/datasets.php?id={id}`: Lấy thông tin chi tiết của một tập dữ liệu.
- `POST /api/datasets.php`: Tiếp nhận tệp tải lên (multipart/form-data) để nhập tập dữ liệu mới.
- `POST /api/mining.php`: Tiếp nhận yêu cầu khai phá dữ liệu (Content-Type: application/json, RFC 8259 [15]). Endpoint này không sử dụng tham số truy vấn `action`.

---

## 7. PHƯƠNG PHÁP NGHIÊN CỨU THỰC NGHIỆM (RESEARCH METHODOLOGY)

### 7.1 Tập Dữ Liệu Thực Nghiệm
Nghiên cứu sử dụng tập dữ liệu chuẩn hóa **UCI Mushroom** (`agaricus-lepiota.data`) [7]:
- **Số lượng bản ghi giao dịch ($N$):** 8,124 giao dịch hoàn chỉnh.
- **Cấu trúc trường thuộc tính:** 23 trường phân loại vật lý (gồm trường lớp nhãn $c_1$ và 22 trường thuộc tính hình thái sinh học từ $c_2$ đến $c_{23}$).
- **Không gian mục chuẩn hóa:** Mỗi mục được mã hóa theo vị trí thuộc tính $c_j=\text{value}$, tạo thành tổng cộng **119 mục phân loại phân biệt**. Ký tự khuyết thiếu `'?'` được bảo toàn như một giá trị phân loại hợp lệ.
- **Bản quyền dữ liệu:** Giấy phép Creative Commons Attribution 4.0 International (CC BY 4.0) [7].

### 7.2 Lịch Sử Hiệu Chỉnh Ma Trận Ngưỡng Hỗ Trợ (Support-Matrix Revision Disclosure)
Nhằm bảo đảm tính minh bạch học thuật:
- **Ma trận đăng ký ban đầu:** $[0.20, 0.15, 0.10, 0.075, 0.05]$.
- **Kết quả thăm dò khả thi tiền hình thức (Pre-formal Feasibility Probe):** Thử nghiệm thăm dò cho thấy các ngưỡng dưới $0.35$ vi phạm giới hạn bảo vệ thời gian thực thi trên tập dữ liệu Mushroom và cấu hình hiện tại (ngưỡng $0.25$ vượt giới hạn thời gian chạy 30 giây của Apriori; ngưỡng $0.30$ hoàn tất Apriori nhưng sinh ra hơn 50,000 luật kết hợp, vượt giới hạn tài nguyên).
- **Ma trận chính thức được phê duyệt:** Đúng một lần điều chỉnh có kiểm soát đã được thực hiện trước khi thu thập dữ liệu hình thức:
  $$\text{min\_support} \in [0.60, 0.50, 0.45, 0.40, 0.35]$$
  tương ứng với số giao dịch yêu cầu lần lượt là: **4,875, 4,062, 3,656, 3,250, và 2,844 giao dịch**. Ngưỡng độ tin cậy được cố định ở $\text{min\_confidence} = 0.75$.

### 7.3 Giao Thức Thu Thập Dữ Liệu Hình Thức (RQ1 / RQ2)
- Mỗi ngưỡng hỗ trợ thực hiện 2 lần chạy làm nóng (*warmup iterations*), tiếp nối bởi **10 lần lặp chính thức** được xáo trộn ngẫu nhiên tất định (*deterministic shuffle*, seed = 42).
- Thời gian thực thi `runtime_ms` đo lường thuần túy thời gian thuật toán Apriori [2] chạy trên tập giao dịch đã nạp sẵn trong bộ nhớ RAM, tách biệt hoàn toàn khỏi thời gian nạp cơ sở dữ liệu, thời gian sinh luật kết hợp và thời gian dựng giao diện.
- Thống kê phi tham số: Báo cáo giá trị **Trung vị (Median)** và **Khoảng liên phân vị (IQR)** theo phương pháp bản lề Tukey nhằm giảm độ nhạy của thống kê tổng hợp đối với các quan sát cực trị. Toàn bộ các quan sát thô đều được lưu giữ đầy đủ, không có dữ liệu nào bị loại bỏ.

### 7.4 Giao Thức Đo Kiểm Trực Quan Hóa Đối Chứng (RQ3)
- Môi trường trình duyệt cô lập: Microsoft Edge 151 (Chromium Engine), độ phân giải cửa sổ cố định $1440 \times 900$, Device Pixel Ratio = 1.0, kích thước khung vẽ đồ họa chuẩn $800 \times 600\text{ px}$.
- Ba thư viện đối chứng: **D3.js v7.9.0** (triển khai SVG) [5], [8], **Chart.js v4.4.8** (triển khai Canvas) [9], **Apache ECharts v5.6.0** (triển khai Canvas) [6], [10].
- Khối lượng dữ liệu điểm phân tán cố định: $N \in [100, 1000, 5000, 10000]$.
- Thước đo thời gian chuẩn hóa: **Độ trễ quan sát hai khung hình** (*render-to-two-frame-observation latency*) sử dụng hai lệnh `requestAnimationFrame` liên tiếp.
- Tổng cộng: 3 thư viện $\times$ 4 quy mô $\times$ 10 lần lặp = **120 quan sát hình thức hoàn chỉnh**.

---

## 8. KẾT QUẢ THỰC NGHIỆM (EXPERIMENTAL RESULTS)

### 8.1 Kết Quả RQ1: Ảnh Hưởng Của Ngưỡng Hỗ Trợ

Bảng T1 tổng hợp toàn bộ kết quả thực nghiệm hình thức cho RQ1 trên tập dữ liệu UCI Mushroom [7].

#### Bảng T1: Tóm Tắt Ảnh Hưởng Của Ngưỡng Hỗ Trợ Đến Không Gian Tìm Kiếm, Khối Lượng Mẫu và Thời Gian Thực Thi (RQ1)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| $\text{min\_support}$ | Số Lượng Giao Dịch Yêu Cầu | Ứng Viên Sinh Ra | Ứng Viên Bị Cắt Tỉa | Ứng Viên Đánh Giá | Tập Mục Phổ Biến | Số Lượng Luật ($\text{conf} \ge 0.75$) | Độ Dài $k_{\max}$ | Thời Gian Trung Vị Apriori (ms) | IQR Thời Gian (ms) | Tỷ Lệ Cắt Tỉa Tổng Thể |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **0.60** | 4,875 | 185 | 11 | 174 | 51 | 223 | 5 | **523.072** | 317.164 | 0.059459 |
| **0.50** | 4,062 | 336 | 29 | 307 | 153 | 664 | 5 | **1,424.078** | 238.000 | 0.086310 |
| **0.45** | 3,656 | 641 | 115 | 526 | 329 | 1,859 | 6 | **3,322.764** | 551.320 | 0.179407 |
| **0.40** | 3,250 | 1,104 | 280 | 824 | 565 | 3,576 | 7 | **5,737.617** | 1,270.777 | 0.253623 |
| **0.35** | 2,844 | 2,131 | 624 | 1,507 | 1,189 | 11,055 | 7 | **14,047.443** | 5,549.082 | 0.292820 |

---

#### Phân Tích Chi Tiết RQ1

1. **Thời Gian Thực Thi (Hình F1):**  
   Khi ngưỡng hỗ trợ giảm dần từ $0.60$ xuống $0.35$, thời gian thực thi trung vị tăng trưởng từ $523.072\text{ ms}$ lên $14,047.443\text{ ms}$. Động thái gia tăng này phản ánh sự mở rộng của khối lượng đánh giá độ hỗ trợ trên tập dữ liệu giao dịch trong bộ nhớ khi số lượng ứng viên sống sót gia tăng.

```text
[Hình F1: Thời Gian Thực Thi Apriori theo Ngưỡng Hỗ Trợ Tối Thiểu (RQ1)]
File: experiments/figures/F1_apriori_runtime_vs_support.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

2. **Không Gian Tìm Kiếm Ứng Viên (Hình F2):**  
   Số lượng ứng viên sinh ra mở rộng đáng kể từ $185$ ứng viên tại ngưỡng $0.60$ lên $2,131$ ứng viên tại ngưỡng $0.35$. Số lượng ứng viên sống sót đi vào bước đánh giá độ hỗ trợ tăng từ $174$ lên $1,507$, trong khi số lượng ứng viên bị loại bỏ sớm bởi cơ chế cắt tỉa tăng từ $11$ lên $624$.

```text
[Hình F2: Khối Lượng Không Gian Ứng Viên theo Ngưỡng Hỗ Trợ Tối Thiểu (RQ1 / RQ2)]
File: experiments/figures/F2_candidate_volume_vs_support.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

3. **Khối Lượng Mẫu Phát Hiện (Hình F3):**  
   Số lượng tập mục phổ biến tăng từ $51$ lên $1,189$ tập mục. Tương ứng, số lượng luật kết hợp thỏa mãn $\text{min\_confidence} \ge 0.75$ tăng mạnh từ $223$ lên $11,055$ luật, khẳng định tính nhạy cảm cao của không gian mẫu đối với ngưỡng lọc hỗ trợ.

```text
[Hình F3: Khối Lượng Tập Mục Phổ Biến và Luật Kết Hợp theo Ngưỡng Hỗ Trợ (RQ1)]
File: experiments/figures/F3_pattern_output_vs_support.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

---

### 8.2 Kết Quả RQ2: Động Thái và Hiệu Quả Cắt Tỉa Apriori

Bảng T2 và Bảng T2b (phụ lục) cung cấp bằng chứng định lượng về hiệu quả của cơ chế cắt tỉa tập con [2].

#### Bảng T2: Tóm Tắt Tỷ Lệ Cắt Tỉa Ứng Viên Tổng Thể theo Ngưỡng Hỗ Trợ (RQ2)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| $\text{min\_support}$ | Ứng Viên Sinh Ra ($C_k$) | Ứng Viên Bị Cắt Tỉa | Ứng Viên Đánh Giá Độ Hỗ Trợ | Tỷ Lệ Cắt Tỉa Tổng Thể |
| :---: | :---: | :---: | :---: | :---: |
| **0.60** | 185 | 11 | 174 | **5.95%** (0.059459) |
| **0.50** | 336 | 29 | 307 | **8.63%** (0.086310) |
| **0.45** | 641 | 115 | 526 | **17.94%** (0.179407) |
| **0.40** | 1,104 | 280 | 824 | **25.36%** (0.253623) |
| **0.35** | 2,131 | 624 | 1,507 | **29.28%** (0.292820) |

---

#### Phân Tích Động Thái Cắt Tỉa Phân Tầng (Hình F4)

- **Bậc $k = 1$ (`singleton_scan`):** Quét 119 mục đơn phân loại; số lượng cắt tỉa bằng 0 theo định nghĩa vì đơn mục không có tập con thực sự.
- **Bậc $k = 2$ (`join_prune`):** Giai đoạn kết nối - cắt tỉa bắt đầu áp dụng từ $k \ge 2$ [2]. Tuy nhiên, tại $k = 2$, tất cả các cặp mục được sinh ra từ $L_1 \Join L_1$ đều có các tập con độ dài 1 nằm trong $L_1$ theo định nghĩa, do đó số lượng ứng viên bị tỉa tại $k = 2$ bằng 0 trên mọi ngưỡng hỗ trợ.
- **Bậc $k \ge 3$ (`join_prune`):** Cắt tỉa tập con thực tế bắt đầu loại bỏ ứng viên từ bậc $k = 3$ và phát huy hiệu quả ở các bậc cao [2]:
  - Tại $\text{min\_support} = 0.60$: Tỷ lệ cắt tỉa đạt $17.4\%$ ở $k=3$, $46.2\%$ ở $k=4$, và $50.0\%$ ở $k=5$.
  - Tại $\text{min\_support} = 0.35$: Cắt tỉa loại bỏ $208 / 563$ ứng viên ($36.9\%$) tại $k=3$, loại bỏ $243 / 649$ ứng viên ($37.4\%$) tại $k=4$, loại bỏ $134 / 390$ ứng viên ($34.4\%$) tại $k=5$, và $34 / 118$ ứng viên ($28.8\%$) tại $k=6$.

```text
[Hình F4: Động Thái Phân Tầng Ứng Viên và Tỷ Lệ Cắt Tỉa Qua Toàn Bộ 5 Ngưỡng Hỗ Trợ (RQ2)]
File: experiments/figures/F4_pruning_dynamics_per_level.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

---

### 8.3 Kết Quả RQ3: So Sánh Hiệu Năng Trực Quan Hóa Front-End

Bảng T3 tổng hợp các quan sát độ trễ hiển thị và cập nhật dữ liệu của ba thư viện đồ họa web [8], [9], [10].

#### Bảng T3: So Sánh Độ Trễ Khởi Tạo và Cập Nhật Dữ Liệu của D3.js, Chart.js và Apache ECharts (RQ3)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| Thư Viện Đồ Họa | Phiên Bản | Kiến Trúc Dựng Hình | Quy Mô Dữ Liệu ($N$) | Số Lần Chạy Hợp Lệ | Độ Trễ Trung Vị Khởi Tạo (ms) | IQR Khởi Tạo (ms) | Độ Trễ Trung Vị Cập Nhật (ms) | IQR Cập Nhật (ms) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Chart.js** [9] | 4.4.8 | Canvas | **100** | 10 / 10 | **18.000** | 16.700 | **16.350** | 0.600 |
| **Chart.js** [9] | 4.4.8 | Canvas | **1,000** | 10 / 10 | **17.500** | 1.800 | **16.950** | 16.600 |
| **Chart.js** [9] | 4.4.8 | Canvas | **5,000** | 10 / 10 | **42.400** | 4.900 | **39.800** | 7.900 |
| **Chart.js** [9] | 4.4.8 | Canvas | **10,000** | 10 / 10 | **70.550** | 14.800 | **60.950** | 11.700 |
| **D3.js** [8] | 7.9.0 | SVG | **100** | 10 / 10 | **17.300** | 1.200 | **17.750** | 15.100 |
| **D3.js** [8] | 7.9.0 | SVG | **1,000** | 10 / 10 | **18.250** | 15.000 | **17.900** | 13.000 |
| **D3.js** [8] | 7.9.0 | SVG | **5,000** | 10 / 10 | **72.750** | 10.100 | **57.650** | 10.300 |
| **D3.js** [8] | 7.9.0 | SVG | **10,000** | 10 / 10 | **138.600** | 26.600 | **117.700** | 19.200 |
| **Apache ECharts** [10] | 5.6.0 | Canvas | **100** | 10 / 10 | **24.850** | 15.700 | **17.100** | 15.900 |
| **Apache ECharts** [10] | 5.6.0 | Canvas | **1,000** | 10 / 10 | **27.550** | 7.300 | **32.300** | 8.800 |
| **Apache ECharts** [10] | 5.6.0 | Canvas | **5,000** | 10 / 10 | **111.000** | 8.400 | **96.250** | 8.900 |
| **Apache ECharts** [10] | 5.6.0 | Canvas | **10,000** | 10 / 10 | **222.600** | 38.400 | **195.800** | 8.300 |

---

#### Phân Tích Hiệu Năng Trực Quan Hóa (Hình F5 và F6)

1. **Vùng Dữ Liệu Nhỏ ($N \le 1,000$):**  
   Tại $N \le 1,000$, các quan sát trung vị duy trì gần mức một đến hai khoảng khung hình 60 Hz ($16.4\text{ ms} - 32.3\text{ ms}$). Do giao thức double-rAF bị lượng tử hóa theo khung hình (*frame-quantized*), các chênh lệch nhỏ trong vùng này không nên bị suy diễn quá mức.

2. **Vùng Dữ Liệu Dày Đặc ($N \ge 5,000$):**  
   Khi khối lượng điểm tăng lên $10,000$, Chart.js [9] ghi nhận độ trễ trung vị thấp nhất ($70.550\text{ ms}$ khởi tạo / $60.950\text{ ms}$ cập nhật), D3.js [8] ghi nhận mức trung gian ($138.600\text{ ms}$ khởi tạo / $117.700\text{ ms}$ cập nhật), và Apache ECharts [10] đạt $222.600\text{ ms}$ khởi tạo / $195.800\text{ ms}$ cập nhật dưới chế độ vẽ tiêu chuẩn. Xu hướng thay đổi này nhất quán với các khác biệt về kiến trúc dựng hình và chi phí xử lý khung làm việc của từng thư viện; thực nghiệm đối chứng này không phân lập riêng lẻ các cơ chế nguyên nhân nội bộ.

3. **So Sánh Cập Nhật Dữ Liệu Tại Chỗ vs. Khởi Tạo Lần Đầu:**  
   Tại các quy mô lớn ($N = 5,000$ và $N = 10,000$), độ trễ cập nhật dữ liệu tại chỗ thấp hơn độ trễ khởi tạo ban đầu trên cả ba thư viện (ví dụ D3.js [8] từ $138.600\text{ ms}$ giảm xuống $117.700\text{ ms}$; Chart.js [9] từ $70.550\text{ ms}$ giảm xuống $60.950\text{ ms}$).

```text
[Hình F5: Độ Trễ Khởi Tạo Biểu Đồ Ban Đầu theo Quy Mô Dữ Liệu (RQ3)]
File: experiments/figures/F5_visualization_initial_render.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.

[Hình F6: Độ Trễ Cập Nhật Dữ Liệu Tại Chỗ theo Quy Mô Dữ Liệu (RQ3)]
File: experiments/figures/F6_visualization_update.svg
Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả.
```

---

## 9. THẢO LUẬN (DISCUSSION)

### 9.1 Mối Quan Hệ Giữa Động Thái Thuật Toán và Ứng Dụng Web
Kết quả thực nghiệm từ RQ1 và RQ2 cung cấp những hiểu biết quan trọng cho việc thiết kế các hệ thống khai phá dữ liệu web:
- **Tác động của không gian mẫu bùng nổ:** Khi hạ ngưỡng hỗ trợ, số lượng ứng viên và luật kết hợp tăng vọt. Nếu không có cơ chế giới hạn bảo vệ (*guardrails*) và chính sách trả về dữ liệu Top-N rút gọn phía API, máy chủ web có thể dễ dàng bị cạn kiệt bộ nhớ hoặc vượt quá giới hạn thời gian phản hồi HTTP.
- **Vai trò bảo vệ của Cắt tỉa Apriori:** Tỷ lệ cắt tỉa tăng dần lên gần $30\%$ cho thấy tính chất Apriori [2] đã ngăn chặn hàng trăm ứng viên không phổ biến tham gia vào bước đánh giá độ hỗ trợ trong bộ nhớ. Điều này khẳng định giá trị của việc hiện thực hóa mô-đun cắt tỉa tối ưu trong các dịch vụ web backend.

### 9.2 Lựa Chọn Thư Viện Trực Quan Hóa Trên Bảng Điều Khiển Web
Kết quả RQ3 lý giải tính hợp lý của kiến trúc hệ thống:
- Mặc dù Chart.js [9] đạt độ trễ thấp hơn ở tác vụ vẽ điểm phân tán dày đặc, Apache ECharts [6], [10] cung cấp hệ sinh thái tính năng phong phú hơn (hỗ trợ ma trận nhiệt tương quan, biểu đồ thanh phân tầng linh hoạt và công cụ chuyển đổi chuỗi dữ liệu khai phá trực quan).
- Trong ứng dụng thực tế của bảng điều khiển web, đa số các tập mẫu phổ biến và luật kết hợp được lọc người dùng nằm trong khoảng $N \le 1,000$, nơi độ trễ của Apache ECharts [10] ($17.1\text{ ms} - 32.3\text{ ms}$) hoàn toàn đáp ứng tốt tính mượt mà của giao diện.

---

## 10. ĐE DỌA GIÁ TRỊ THỰC NGHIỆM VÀ HẠN CHẾ (THREATS TO VALIDITY & LIMITATIONS)

### 10.1 Hạn Chế Của Thực Nghiệm Khai Phá (RQ1 & RQ2)
1. **Phạm Vi Tập Dữ Liệu Đơn Lẻ:** Toàn bộ quan sát hình thức được thực hiện trên tập dữ liệu chuẩn UCI Mushroom (`agaricus-lepiota.data`, $N = 8,124$) [7]. Các đặc tính phân phối của dữ liệu giao dịch thương mại thưa (*sparse market basket data*) có thể mang lại động thái cắt tỉa khác biệt.
2. **Hiệu Chỉnh Ma Trận Hỗ Trợ:** Ma trận hỗ trợ chính thức $[0.35 - 0.60]$ được hiệu chỉnh sau bước thăm dò khả thi tiền hình thức để bảo đảm tính khả thi tính toán trên hệ thống; các ngưỡng hỗ trợ rất thấp ($< 0.30$) chưa được đo lường do vi phạm giới hạn thời gian chạy và giới hạn số lượng luật.
3. **Thiếu Đường Cơ Sở Đo Thời Gian Không Cắt Tỉa:** Do hệ thống không triển khai phiên bản Apriori thô loại bỏ hoàn toàn bước tỉa, hiệu quả cắt tỉa chỉ được định lượng qua số lượng ứng viên và tỷ lệ cắt tỉa, không thể suy diễn tỷ lệ tăng tốc thời gian thực thi (*wall-clock speedup*).
4. **Tính Đơn Điệu Của Ứng Viên:** Động thái giảm dần của số lượng ứng viên theo $k$ là một kết quả chẩn đoán thực nghiệm trên tập dữ liệu cụ thể, không phải là một định lý toán học bất biến trên mọi phân phối dữ liệu.
5. **Cố Định Ngưỡng Tin Cậy:** Mọi luật kết hợp được khảo sát ở mức $\text{min\_confidence} = 0.75$.

### 10.2 Hạn Chế Của Thực Nghiệm Trực Quan Hóa (RQ3)
1. **Ràng Buộc Kiến Trúc Dựng Hình:** D3.js được triển khai với SVG DOM [5], [8] trong khi Chart.js [9] và ECharts [6], [10] sử dụng Canvas. Kiến trúc dựng hình là một phần cấu thành của đối tượng nghiên cứu, không phải một biến số thuật toán cô lập hoàn toàn.
2. **Lượng Tử Hóa Khung Hình (Frame Quantization):** Thước đo double-rAF chịu ảnh hưởng bởi chu kỳ quét khung hình 60 Hz ($\sim 16.7\text{ ms}$). Thước đo này đo lường độ trễ từ lúc bắt đầu vẽ đến khi ghi nhận khung hình quan sát thứ hai, không đo lường thời gian hoàn tất phần cứng GPU (*GPU completion*) hay thời gian quét điểm ảnh màn hình (*paint/presentation completion*).
3. **Bố Cục Vùng Vẽ Nội Bộ:** Kích thước stage tổng thể cố định $800 \times 600\text{ px}$, nhưng lề biên và tính toán trục tọa độ nội bộ tuân theo cơ chế tự động của từng thư viện.
4. **Bộ Thu Gom Rác Trình Duyệt (Garbage Collection):** Hoạt động thu gom rác nền của trình duyệt là yếu tố nhiễu không thể kiểm soát tuyệt đối, được giảm thiểu bằng cách sử dụng thống kê Trung vị và IQR.
5. **Phạm Vi Tác Vụ:** Chỉ khảo sát biểu đồ phân tán 2D với dữ liệu số thuần túy và tắt toàn bộ hiệu ứng chuyển động (*animations disabled*).

---

## 11. KẾT LUẬN (CONCLUSION)

Đề tài đã hoàn thành các mục tiêu nghiên cứu và phát triển phần mềm đề ra cho giai đoạn giữa kỳ:
- Xây dựng thành công hệ thống ứng dụng web bảng điều khiển tương tác, tích hợp giữa tầng xử lý nghiệp vụ PHP phân lớp nhẹ [13], cơ sở dữ liệu MySQL [14] và giao diện người dùng ECharts [10] / Bootstrap [11] đáp ứng.
- Động cơ khai phá Apriori được hiện thực hóa với kiến trúc mô-đun hóa cao [2], cung cấp đầy đủ số liệu chẩn đoán cắt tỉa chi tiết qua từng cấp độ $k$.
- Thiết lập khung thực nghiệm có kiểm soát với quy trình đóng băng dữ liệu nghiêm ngặt, trả lời thỏa đáng ba câu hỏi nghiên cứu RQ1, RQ2 và RQ3 với các bằng chứng thực nghiệm có khả năng tái lập tất định.

---

## 12. HƯỚNG PHÁT TRIỂN TƯƠNG LAI (FUTURE WORK)

Trong giai đoạn cuối kỳ, dự án định hướng mở rộng các nội dung sau:
1. **Tích hợp Thuật toán Đối chứng FP-Growth:** Hiện thực hóa động cơ FP-Growth song song trên PHP [3] để thực hiện đối sánh trực tiếp về thời gian thực thi và mức tiêu thụ bộ nhớ với Apriori [2] trên cùng một giao diện bảng điều khiển.
2. **Mở Rộng Hỗ Trợ Đa Tập Dữ Liệu:** Bổ sung các tập dữ liệu giao dịch thương mại bán lẻ quy mô lớn (ví dụ *Online Retail*, *Instacart Basket Data*).
3. **Tối Ưu Hóa Bộ Đệm Phân Tán (Distributed Caching):** Áp dụng giải pháp lưu trữ bộ đệm cho các tập mục phổ biến thường dùng nhằm tăng tốc độ phản hồi cho các truy vấn lặp lại trên bảng điều khiển web.

---

## 13. TÀI LIỆU THAM KHẢO (REFERENCES)

1. **[1] Agrawal, R., Imieliński, T., & Swami, A. (1993).** *Mining Association Rules between Sets of Items in Large Databases.* In *Proceedings of the 1993 ACM SIGMOD International Conference on Management of Data (SIGMOD '93)*, Washington, D.C., USA, pp. 207–216. DOI: [10.1145/170035.170072](https://doi.org/10.1145/170035.170072).
2. **[2] Agrawal, R., & Srikant, R. (1994).** *Fast Algorithms for Mining Association Rules in Large Databases.* In *Proceedings of the 20th International Conference on Very Large Data Bases (VLDB '94)*, Santiago, Chile, pp. 487–499. URL: [https://www.vldb.org/conf/1994/P487.PDF](https://www.vldb.org/conf/1994/P487.PDF).
3. **[3] Han, J., Pei, J., & Yin, Y. (2000).** *Mining Frequent Patterns without Candidate Generation.* In *Proceedings of the 2000 ACM SIGMOD International Conference on Management of Data (SIGMOD '00)*, Dallas, Texas, USA, pp. 1–12. DOI: [10.1145/342009.335372](https://doi.org/10.1145/342009.335372).
4. **[4] Tan, P.-N., Steinbach, M., Karpatne, A., & Kumar, V. (2018).** *Introduction to Data Mining (2nd Edition).* Pearson, Boston, MA, USA. ISBN-13: 978-0133128901.
5. **[5] Bostock, M., Ogievetsky, V., & Heer, J. (2011).** *D³: Data-Driven Documents.* *IEEE Transactions on Visualization and Computer Graphics*, 17(12), pp. 2301–2309. DOI: [10.1109/TVCG.2011.185](https://doi.org/10.1109/TVCG.2011.185).
6. **[6] Li, D., Mei, H., Shen, Y., Su, S., Zhang, W., Wang, J., Zu, M., & Chen, W. (2018).** *ECharts: A Declarative Framework for Rapid Construction of Web-based Visualization.* *Visual Informatics*, 2(2), pp. 136–146. DOI: [10.1016/j.visinf.2018.04.011](https://doi.org/10.1016/j.visinf.2018.04.011).
7. **[7] UCI Machine Learning Repository: Mushroom Data Set.** *Agaricus and Lepiota Mushroom Dataset (agaricus-lepiota.data).* Donor: Jeff Schlimmer (1987). DOI: [10.24432/C59591](https://doi.org/10.24432/C59591). URL: [https://archive.ics.uci.edu/dataset/73/mushroom](https://archive.ics.uci.edu/dataset/73/mushroom).
8. **[8] Bostock, M., & D3 Contributors (2024).** *D3.js: JavaScript library for visualizing data (Version 7.9.0).* Package: `npm:d3@7.9.0`. URL: [https://d3js.org/](https://d3js.org/).
9. **[9] Chart.js Open Source Project (2025).** *Chart.js: Simple yet flexible JavaScript charting for designers & developers (Version 4.4.8).* Package: `npm:chart.js@4.4.8`. URL: [https://www.chartjs.org/](https://www.chartjs.org/).
10. **[10] The Apache Software Foundation (2025).** *Apache ECharts: An Open Source JavaScript Visualization Library (Version 5.6.0).* Package: `npm:echarts@5.6.0`. URL: [https://echarts.apache.org/](https://echarts.apache.org/).
11. **[11] Bootstrap Authors (2024).** *Bootstrap: Powerful, extensible, and feature-packed frontend toolkit (Version 5.3.8).* Package: `npm:bootstrap@5.3.8`. URL: [https://getbootstrap.com/](https://getbootstrap.com/).
12. **[12] OpenJS Foundation (2023).** *jQuery: Fast, small, and feature-rich JavaScript library (Version 3.7.1).* URL: [https://jquery.com/](https://jquery.com/).
13. **[13] The PHP Group (2024).** *PHP Manual: Language Reference & Runtime Architecture.* Measured Runtime: PHP 8.3.30 (Formal Benchmark Environment) / Language Target: PHP 8.2+. URL: [https://www.php.net/docs.php](https://www.php.net/docs.php).
14. **[14] Oracle Corporation (2024).** *MySQL 8.4 Reference Manual: InnoDB Storage Engine & Performance.* Measured Server: MySQL 8.4.3 (Formal Benchmark Environment). URL: [https://dev.mysql.com/doc/refman/8.4/en/](https://dev.mysql.com/doc/refman/8.4/en/).
15. **[15] Bray, T. (Ed.) (2017).** *RFC 8259: The JavaScript Object Notation (JSON) Data Interchange Format.* IETF RFC 8259, STD 90. DOI: [10.17487/RFC8259](https://doi.org/10.17487/RFC8259). URL: [https://www.rfc-editor.org/info/rfc8259](https://www.rfc-editor.org/info/rfc8259).

---

## PHỤ LỤC: BẢNG SỐ LIỆU CẮT TỈA PHÂN TẦNG CHI TIẾT (APPENDIX - TABLE T2b)

#### Bảng T2b: Chi Tiết Động Thái Cắt Tỉa Qua Từng Bậc Độ Dài $k$ Cho Toàn Bộ 5 Ngưỡng Hỗ Trợ (RQ2)
*(Nguồn: Thực nghiệm đối chứng có kiểm soát của nhóm tác giả)*

| $\text{min\_support}$ | Bậc $k$ | Phân Loại Giai Đoạn | Ứng Viên Sinh Ra | Ứng Viên Bị Tỉa | Ứng Viên Đánh Giá | Tập Mục Phổ Biến | Tỷ Lệ Cắt Tỉa |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| 0.60 | 1 | `singleton_scan` | 119 | 0 | 119 | 14 | 0.000000 |
| 0.60 | 2 | `join_prune` | 46 | 0 | 46 | 21 | 0.000000 |
| 0.60 | 3 | `join_prune` | 15 | 3 | 12 | 12 | 0.200000 |
| 0.60 | 4 | `join_prune` | 4 | 7 | 3 | 3 | 0.700000 |
| 0.60 | 5 | `join_prune` | 1 | 1 | 0 | 1 | 1.000000 |
| 0.50 | 1 | `singleton_scan` | 119 | 0 | 119 | 20 | 0.000000 |
| 0.50 | 2 | `join_prune` | 105 | 0 | 105 | 53 | 0.000000 |
| 0.50 | 3 | `join_prune` | 84 | 14 | 70 | 54 | 0.166667 |
| 0.50 | 4 | `join_prune` | 26 | 13 | 13 | 24 | 0.500000 |
| 0.50 | 5 | `join_prune` | 2 | 2 | 0 | 2 | 1.000000 |
| 0.45 | 1 | `singleton_scan` | 119 | 0 | 119 | 23 | 0.000000 |
| 0.45 | 2 | `join_prune` | 168 | 0 | 168 | 98 | 0.000000 |
| 0.45 | 3 | `join_prune` | 239 | 50 | 189 | 132 | 0.209205 |
| 0.45 | 4 | `join_prune` | 101 | 55 | 46 | 66 | 0.544554 |
| 0.45 | 5 | `join_prune` | 13 | 9 | 4 | 9 | 0.692308 |
| 0.45 | 6 | `join_prune` | 1 | 1 | 0 | 1 | 1.000000 |
| 0.40 | 1 | `singleton_scan` | 119 | 0 | 119 | 28 | 0.000000 |
| 0.40 | 2 | `join_prune` | 260 | 0 | 260 | 144 | 0.000000 |
| 0.40 | 3 | `join_prune` | 440 | 115 | 325 | 224 | 0.261364 |
| 0.40 | 4 | `join_prune` | 233 | 129 | 104 | 136 | 0.553648 |
| 0.40 | 5 | `join_prune` | 47 | 32 | 15 | 30 | 0.680851 |
| 0.40 | 6 | `join_prune` | 4 | 3 | 1 | 2 | 0.750000 |
| 0.40 | 7 | `join_prune` | 1 | 1 | 0 | 1 | 1.000000 |
| 0.35 | 1 | `singleton_scan` | 119 | 0 | 119 | 33 | 0.000000 |
| 0.35 | 2 | `join_prune` | 382 | 0 | 382 | 227 | 0.000000 |
| 0.35 | 3 | `join_prune` | 884 | 208 | 676 | 450 | 0.235294 |
| 0.35 | 4 | `join_prune` | 569 | 243 | 326 | 336 | 0.427065 |
| 0.35 | 5 | `join_prune` | 156 | 134 | 22 | 119 | 0.858974 |
| 0.35 | 6 | `join_prune` | 19 | 34 | 15 | 16 | 0.693878 |
| 0.35 | 7 | `join_prune` | 2 | 5 | 3 | 1 | 0.714286 |
