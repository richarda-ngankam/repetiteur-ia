class Child {
  const Child({
    required this.id,
    required this.firstName,
    required this.age,
    required this.levelCode,
    required this.trackCode,
    required this.myRole,
  });

  final String id;
  final String firstName;
  final int age;
  final String levelCode;
  final String? trackCode;
  final String myRole; // payer | follower

  factory Child.fromJson(Map<String, dynamic> json) => Child(
    id: json['id'] as String,
    firstName: json['first_name'] as String,
    age: json['age'] as int,
    levelCode: json['level_code'] as String,
    trackCode: json['track_code'] as String?,
    myRole: json['my_role'] as String,
  );
}