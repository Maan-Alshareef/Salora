import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/venue_model.dart';

class CompareProvider extends ChangeNotifier {
  static const _key='salora_compare_venues_v2';
  final List<VenueModel> _items=[];
  CompareProvider(){_restore();}
  List<VenueModel> get items=>List.unmodifiable(_items);
  bool contains(String id)=>_items.any((v)=>v.id==id);
  bool get canOpenComparison=>_items.length>=2;
  Future<void> _restore() async {try{final prefs=await SharedPreferences.getInstance();final raw=prefs.getStringList(_key)??const[];_items..clear()..addAll(raw.map((e)=>VenueModel.fromJson(Map<String,dynamic>.from(jsonDecode(e) as Map))).take(3));notifyListeners();}catch(_){}}
  Future<void> _save() async {final prefs=await SharedPreferences.getInstance();await prefs.setStringList(_key,_items.map((v)=>jsonEncode(v.toCacheJson())).toList());}
  void addOrReplace(VenueModel venue){if(contains(venue.id))return;if(_items.length>=3)_items.removeAt(0);_items.add(venue);_save();notifyListeners();}
  void remove(String id){final before=_items.length;_items.removeWhere((v)=>v.id==id);if(before!=_items.length){_save();notifyListeners();}}
  bool toggle(VenueModel venue){contains(venue.id)?remove(venue.id):addOrReplace(venue);return true;}
  void clear(){if(_items.isEmpty)return;_items.clear();_save();notifyListeners();}
}
